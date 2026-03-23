<?php

declare(strict_types=1);

namespace App\Platform\Http\Client;

use App\Platform\Http\Client\Exception\CurlTransportException;
use App\Platform\Http\Client\Exception\HttpTimeoutException;
use App\Platform\Http\Client\Exception\HttpTransportException;
use App\Platform\Runtime\Deadline;

final class CurlTransport implements HttpTransport
{
    public function send(HttpRequest $request, Deadline $deadline, int $attempt): HttpResponse
    {
        if ($deadline->isExhausted()) {
            throw HttpTimeoutException::forBudgetExhaustion($request, $attempt);
        }

        $curl = curl_init($request->uri);
        if ($curl === false) {
            throw CurlTransportException::forCurlFailure($request, $attempt, 'Failed to initialize cURL transport');
        }

        $remainingMilliseconds = $deadline->remainingMilliseconds();
        $connectTimeoutMilliseconds = min(
            max(1, (int) ceil($request->options->connectTimeoutSeconds * 1000)),
            max(1, $remainingMilliseconds),
        );

        $curlOptions = [
            CURLOPT_CUSTOMREQUEST => $request->method->value,
            CURLOPT_FOLLOWLOCATION => $request->options->followRedirects,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => $this->normalizeHeaders($request),
            CURLOPT_MAXREDIRS => $request->options->maxRedirects,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => max(1, $remainingMilliseconds),
            CURLOPT_CONNECTTIMEOUT_MS => $connectTimeoutMilliseconds,
        ];

        if ($request->body !== '') {
            $curlOptions[CURLOPT_POSTFIELDS] = $request->body;
        }

        curl_setopt_array($curl, $curlOptions);

        try {
            $rawResponse = curl_exec($curl);
            if ($rawResponse === false) {
                $errorCode = curl_errno($curl);
                $errorMessage = curl_error($curl);

                throw $this->toTransportException($request, $attempt, $errorCode, $errorMessage);
            }

            if (!\is_string($rawResponse)) {
                throw CurlTransportException::forCurlFailure(
                    $request,
                    $attempt,
                    'HTTP transport returned an unexpected non-string response payload',
                );
            }

            $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $rawHeaders = substr($rawResponse, 0, $headerSize);
            $body = substr($rawResponse, $headerSize);

            return new HttpResponse(
                statusCode: $statusCode,
                headers: $this->parseHeaders($rawHeaders),
                body: $body,
            );
        } finally {
            curl_close($curl);
        }
    }

    /**
     * @return list<string>
     */
    private function normalizeHeaders(HttpRequest $request): array
    {
        $headers = $request->headers;
        $hasUserAgent = false;

        foreach ($headers as $name => $_value) {
            if (strcasecmp($name, 'User-Agent') === 0) {
                $hasUserAgent = true;

                break;
            }
        }

        if (!$hasUserAgent && $request->options->userAgent !== null) {
            $headers['User-Agent'] = $request->options->userAgent;
        }

        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[] = $name . ': ' . $value;
        }

        return $normalized;
    }

    /**
     * @return array<string, list<string>>
     */
    private function parseHeaders(string $rawHeaders): array
    {
        $headerBlocks = preg_split("/\r\n\r\n|\n\n|\r\r/", trim($rawHeaders));
        $lastBlock = $headerBlocks !== false && $headerBlocks !== [] ? end($headerBlocks) : false;
        if ($lastBlock === false || $lastBlock === '') {
            return [];
        }

        $headers = [];
        foreach (preg_split("/\r\n|\n|\r/", $lastBlock) ?: [] as $line) {
            $separatorPosition = strpos($line, ':');
            if ($separatorPosition === false) {
                continue;
            }

            $name = trim(substr($line, 0, $separatorPosition));
            $value = trim(substr($line, $separatorPosition + 1));

            if ($name === '') {
                continue;
            }

            $headers[$name] ??= [];
            $headers[$name][] = $value;
        }

        return $headers;
    }

    private function toTransportException(HttpRequest $request, int $attempt, int $errorCode, string $errorMessage): HttpTransportException
    {
        $message = 'HTTP transport error: ' . $errorMessage;

        return match ($errorCode) {
            CURLE_OPERATION_TIMEDOUT => HttpTimeoutException::forTransportTimeout($request, $attempt, $message, $errorCode),
            default => CurlTransportException::forCurlFailure($request, $attempt, $message, $errorCode),
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Platform\Http\Client;

use App\Platform\Http\Client\Exception\HttpTransportException;
use App\Platform\Logging\Logger;

final readonly class ResilientHttpClient implements HttpClient
{
    public function __construct(
        private HttpTransport $transport,
        private Logger $logger,
    ) {}

    public function send(HttpRequest $request): HttpResponse
    {
        $deadline = Deadline::fromSeconds($request->options->timeoutSeconds);
        $retryPolicy = $request->options->retryPolicy;
        $lastRetryableResponse = null;

        for ($attempt = 1; $attempt <= $retryPolicy->maxAttempts; ++$attempt) {
            try {
                $response = $this->transport->send($request, $deadline, $attempt);

                if (!$this->shouldRetryResponse($request, $response, $attempt)) {
                    return $response;
                }

                $lastRetryableResponse = $response;

                $this->logger->warning('HTTP request will be retried after upstream response', [
                    'uri' => $request->uri,
                    'method' => $request->method->value,
                    'upstream' => $request->upstream,
                    'attempt' => $attempt,
                    'status_code' => $response->statusCode,
                ]);
            } catch (HttpTransportException $exception) {
                if (!$this->shouldRetryTransportError($request, $attempt)) {
                    throw $exception;
                }

                $this->logger->warning('HTTP request will be retried after transport failure', [
                    'uri' => $request->uri,
                    'method' => $request->method->value,
                    'upstream' => $request->upstream,
                    'attempt' => $attempt,
                    'error' => $exception->getMessage(),
                ]);
            }

            $this->sleepBackoff($retryPolicy, $attempt, $deadline);
        }

        if ($lastRetryableResponse !== null) {
            return $lastRetryableResponse;
        }

        throw new \LogicException('HTTP retry loop exited without a response or exception');
    }

    private function shouldRetryTransportError(HttpRequest $request, int $attempt): bool
    {
        return $request->options->idempotent
            && $attempt < $request->options->retryPolicy->maxAttempts;
    }

    private function shouldRetryResponse(HttpRequest $request, HttpResponse $response, int $attempt): bool
    {
        return $request->options->idempotent
            && $attempt < $request->options->retryPolicy->maxAttempts
            && \in_array($response->statusCode, $request->options->retryPolicy->retryableStatusCodes, true);
    }

    private function sleepBackoff(RetryPolicy $retryPolicy, int $attempt, Deadline $deadline): void
    {
        $remainingMilliseconds = $deadline->remainingMilliseconds();
        if ($remainingMilliseconds <= 0) {
            return;
        }

        $baseDelay = $retryPolicy->baseDelayMilliseconds * (2 ** max(0, $attempt - 1));
        $cappedDelay = min($retryPolicy->maxDelayMilliseconds, $baseDelay, $remainingMilliseconds);
        if ($cappedDelay <= 0) {
            return;
        }

        $jitteredDelay = $cappedDelay > 1 ? random_int(0, $cappedDelay) : $cappedDelay;
        if ($jitteredDelay <= 0) {
            return;
        }

        usleep($jitteredDelay * 1000);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Http\Client;

use App\Platform\Http\Client\Deadline;
use App\Platform\Http\Client\Exception\CurlTransportException;
use App\Platform\Http\Client\Exception\HttpTransportException;
use App\Platform\Http\Client\HttpClient;
use App\Platform\Http\Client\HttpMethod;
use App\Platform\Http\Client\HttpRequest;
use App\Platform\Http\Client\HttpRequestOptions;
use App\Platform\Http\Client\HttpResponse;
use App\Platform\Http\Client\HttpTransport;
use App\Platform\Http\Client\ResilientHttpClient;
use App\Platform\Http\Client\RetryPolicy;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Platform\Logging\TestLogger;

final class ResilientHttpClientTest extends TestCase
{
    public function testRetriesIdempotentTransportFailures(): void
    {
        $state = new AttemptCounter();
        $client = $this->createClient(
            new class ($state) implements HttpTransport {
                public function __construct(
                    private AttemptCounter $state,
                ) {}

                public function send(HttpRequest $request, Deadline $deadline, int $attempt): HttpResponse
                {
                    ++$this->state->attempts;

                    if ($attempt === 1) {
                        throw CurlTransportException::forCurlFailure($request, $attempt, 'temporary network issue');
                    }

                    return new HttpResponse(200, [], 'ok');
                }
            },
        );

        $response = $client->send(
            new HttpRequest(
                method: HttpMethod::GET,
                uri: 'https://example.test/resource',
                options: new HttpRequestOptions(
                    timeoutSeconds: 1.0,
                    retryPolicy: new RetryPolicy(
                        maxAttempts: 2,
                        baseDelayMilliseconds: 0,
                        maxDelayMilliseconds: 0,
                    ),
                ),
            ),
        );

        self::assertSame(200, $response->statusCode);
        self::assertSame(2, $state->attempts);
    }

    public function testRetriesRetryableStatusCodes(): void
    {
        $state = new AttemptCounter();
        $client = $this->createClient(
            new class ($state) implements HttpTransport {
                public function __construct(
                    private AttemptCounter $state,
                ) {}

                public function send(HttpRequest $request, Deadline $deadline, int $attempt): HttpResponse
                {
                    ++$this->state->attempts;

                    return $attempt === 1
                        ? new HttpResponse(503, [], 'unavailable')
                        : new HttpResponse(200, [], 'ok');
                }
            },
        );

        $response = $client->send(
            new HttpRequest(
                method: HttpMethod::GET,
                uri: 'https://example.test/resource',
                options: new HttpRequestOptions(
                    timeoutSeconds: 1.0,
                    retryPolicy: new RetryPolicy(
                        maxAttempts: 2,
                        baseDelayMilliseconds: 0,
                        maxDelayMilliseconds: 0,
                    ),
                ),
            ),
        );

        self::assertSame(200, $response->statusCode);
        self::assertSame(2, $state->attempts);
    }

    public function testDoesNotRetryNonIdempotentRequests(): void
    {
        $state = new AttemptCounter();
        $client = $this->createClient(
            new class ($state) implements HttpTransport {
                public function __construct(
                    private AttemptCounter $state,
                ) {}

                public function send(HttpRequest $request, Deadline $deadline, int $attempt): HttpResponse
                {
                    ++$this->state->attempts;

                    throw CurlTransportException::forCurlFailure($request, $attempt, 'write request failed');
                }
            },
        );

        $request = new HttpRequest(
            method: HttpMethod::POST,
            uri: 'https://example.test/resource',
            body: '{"name":"value"}',
            options: new HttpRequestOptions(
                timeoutSeconds: 1.0,
                idempotent: false,
                retryPolicy: new RetryPolicy(
                    maxAttempts: 3,
                    baseDelayMilliseconds: 0,
                    maxDelayMilliseconds: 0,
                ),
            ),
        );

        $this->expectException(HttpTransportException::class);

        try {
            $client->send($request);
        } finally {
            self::assertSame(1, $state->attempts);
        }
    }

    private function createClient(HttpTransport $transport): HttpClient
    {
        return new ResilientHttpClient($transport, new TestLogger());
    }
}

final class AttemptCounter
{
    public int $attempts = 0;
}

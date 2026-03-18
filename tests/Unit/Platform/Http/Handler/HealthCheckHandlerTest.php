<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Http\Handler;

use App\Platform\Http\Handler\HealthCheckHandler;
use App\Platform\Http\JsonResponse;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class HealthCheckHandlerTest extends TestCase
{
    /**
     * @throws \JsonException
     */
    public function testHandleReturnsHealthyPayload(): void
    {
        $handler = new HealthCheckHandler(new JsonResponse());

        $response = $handler->handle(new ServerRequest('GET', '/api/v1/health'));
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertIsArray($payload);
        self::assertSame('ok', $payload['status'] ?? null);
        /** @var mixed $timestamp */
        $timestamp = $payload['timestamp'];
        self::assertIsString($timestamp);
        self::assertGreaterThan(0, (int) $timestamp);
    }
}

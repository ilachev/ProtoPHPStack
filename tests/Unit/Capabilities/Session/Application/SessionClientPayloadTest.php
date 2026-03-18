<?php

declare(strict_types=1);

namespace Tests\Unit\Capabilities\Session\Application;

use App\Capabilities\Session\Application\SessionClientPayload;
use App\Capabilities\Session\Domain\Session;
use PHPUnit\Framework\TestCase;

final class SessionClientPayloadTest extends TestCase
{
    public function testFromSessionExtractsTypedPayloadData(): void
    {
        $payload = json_encode([
            'ip' => '192.168.1.1',
            'userAgent' => 'Test Browser',
            'acceptLanguage' => 'en-US',
            'xForwardedFor' => '10.0.0.1',
        ]);

        $session = new Session(
            id: 'test-session-id',
            userId: 123,
            payload: $payload !== false ? $payload : '{}',
            expiresAt: time() + 3600,
            createdAt: time() - 100,
            updatedAt: time(),
        );

        $result = SessionClientPayload::fromSession($session);

        self::assertSame('192.168.1.1', $result->ipAddress);
        self::assertSame('Test Browser', $result->userAgent);
        self::assertSame(
            [
                'acceptLanguage' => 'en-US',
                'xForwardedFor' => '10.0.0.1',
            ],
            $result->attributes,
        );
    }

    public function testFromSessionFallsBackToUnknownPayload(): void
    {
        $session = new Session(
            id: 'test-session-id',
            userId: 123,
            payload: 'invalid-json',
            expiresAt: time() + 3600,
            createdAt: time() - 100,
            updatedAt: time(),
        );

        $result = SessionClientPayload::fromSession($session);

        self::assertSame('unknown', $result->ipAddress);
        self::assertNull($result->userAgent);
        self::assertSame([], $result->attributes);
    }
}

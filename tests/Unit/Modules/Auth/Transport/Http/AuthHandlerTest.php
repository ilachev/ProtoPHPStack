<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Auth\Transport\Http;

use App\Api\V1\LoginRequest;
use App\Api\V1\RefreshTokenRequest;
use App\Modules\Auth\Domain\AuthService;
use App\Modules\Auth\Domain\AuthUser;
use App\Modules\Auth\Transport\Http\AuthHandler;
use App\Modules\Session\Domain\Session;
use App\Modules\Session\Domain\SessionConfig;
use App\Modules\Session\Domain\SessionService;
use App\Modules\Session\Transport\Http\SessionResponseHeaders;
use App\Platform\Http\JsonResponse;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Infrastructure\Logger\TestLogger;
use Tests\Unit\Modules\Auth\Domain\InMemoryAuthUserRepository;
use Tests\Unit\Modules\Auth\Domain\InMemoryRefreshTokenSessionRepository;
use Tests\Unit\Modules\Auth\Domain\InMemorySessionRepository;

final class AuthHandlerTest extends TestCase
{
    private InMemoryAuthUserRepository $userRepository;

    private InMemoryRefreshTokenSessionRepository $refreshTokenSessionRepository;

    private InMemorySessionRepository $sessionRepository;

    private AuthHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = new InMemoryAuthUserRepository();
        $this->refreshTokenSessionRepository = new InMemoryRefreshTokenSessionRepository();
        $this->sessionRepository = new InMemorySessionRepository();

        $sessionService = new SessionService($this->sessionRepository, new TestLogger());
        $sessionConfig = SessionConfig::fromArray([
            'cookie_name' => 'session',
            'cookie_ttl' => 86400,
            'session_ttl' => 3600,
            'use_fingerprint' => false,
        ]);

        $authService = new AuthService(
            $this->userRepository,
            $this->refreshTokenSessionRepository,
            $this->sessionRepository,
            $sessionService,
            $sessionConfig,
        );

        $this->handler = new AuthHandler($authService, new JsonResponse());
    }

    public function testLoginReturnsTokens(): void
    {
        $user = new AuthUser(
            id: 7,
            email: 'dev@example.com',
            passwordHash: password_hash('secret', PASSWORD_DEFAULT),
        );
        $this->userRepository->users[$user->email] = $user;

        $session = new Session(
            id: 'session-id',
            userId: null,
            payload: '{"ip":"127.0.0.1"}',
            expiresAt: time() + 300,
            createdAt: time() - 100,
            updatedAt: time() - 50,
        );
        $this->sessionRepository->save($session);

        $body = (new LoginRequest())
            ->setEmail('dev@example.com')
            ->setPassword('secret')
            ->serializeToJsonString();

        $request = (new ServerRequest('POST', '/api/v1/auth/login'))
            ->withBody(Stream::create($body))
            ->withAttribute('session', $session);

        $response = $this->handler->handle($request);
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertIsArray($payload);
        self::assertSame('session-id', $payload['accessToken']);
        self::assertArrayHasKey('refreshToken', $payload);
        self::assertArrayHasKey('expiresIn', $payload);
    }

    public function testRefreshReturnsSessionOverrideHeader(): void
    {
        $session = new Session(
            id: 'auth-session',
            userId: 3,
            payload: '{"refreshToken":"refresh-123"}',
            expiresAt: time() + 300,
            createdAt: time() - 100,
            updatedAt: time() - 50,
        );
        $currentAnonymousSession = new Session(
            id: 'anonymous-session',
            userId: null,
            payload: '{}',
            expiresAt: time() + 300,
            createdAt: time() - 100,
            updatedAt: time() - 50,
        );
        $this->sessionRepository->save($session);
        $this->sessionRepository->save($currentAnonymousSession);
        $this->refreshTokenSessionRepository->sessions['refresh-123'] = $session;

        $body = (new RefreshTokenRequest())
            ->setRefreshToken('refresh-123')
            ->serializeToJsonString();

        $request = (new ServerRequest('POST', '/api/v1/auth/refresh'))
            ->withBody(Stream::create($body))
            ->withAttribute('session', $currentAnonymousSession);

        $response = $this->handler->handle($request);
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertIsArray($payload);
        self::assertSame('auth-session', $response->getHeaderLine(SessionResponseHeaders::ACTIVE_SESSION_ID));
        self::assertSame('auth-session', $payload['accessToken']);
    }

    public function testLogoutRequestsCookieDeletion(): void
    {
        $session = new Session(
            id: 'session-to-delete',
            userId: 8,
            payload: '{}',
            expiresAt: time() + 300,
            createdAt: time() - 100,
            updatedAt: time() - 50,
        );
        $this->sessionRepository->save($session);

        $request = (new ServerRequest('POST', '/api/v1/auth/logout'))
            ->withAttribute('session', $session);

        $response = $this->handler->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('1', $response->getHeaderLine(SessionResponseHeaders::DESTROY_SESSION));
        self::assertNull($this->sessionRepository->findById('session-to-delete'));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Auth\Domain;

use App\Modules\Auth\Domain\AuthService;
use App\Modules\Auth\Domain\AuthUser;
use App\Modules\Auth\Domain\AuthUserRepository;
use App\Modules\Auth\Domain\RefreshTokenSessionRepository;
use App\Modules\Session\Domain\Session;
use App\Modules\Session\Domain\SessionConfig;
use App\Modules\Session\Domain\SessionRepository;
use App\Modules\Session\Domain\SessionService;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Infrastructure\Logger\TestLogger;

final class AuthServiceTest extends TestCase
{
    private InMemoryAuthUserRepository $userRepository;

    private InMemoryRefreshTokenSessionRepository $refreshTokenSessionRepository;

    private InMemorySessionRepository $sessionRepository;

    private AuthService $authService;

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

        $this->authService = new AuthService(
            $this->userRepository,
            $this->refreshTokenSessionRepository,
            $this->sessionRepository,
            $sessionService,
            $sessionConfig,
        );
    }

    public function testLoginUpdatesExistingSessionAndReturnsTokens(): void
    {
        $user = new AuthUser(
            id: 101,
            email: 'dev@example.com',
            passwordHash: password_hash('secret', PASSWORD_DEFAULT),
        );
        $this->userRepository->users[$user->email] = $user;

        $session = new Session(
            id: 'existing-session',
            userId: null,
            payload: '{"ip":"127.0.0.1"}',
            expiresAt: time() + 100,
            createdAt: time() - 100,
            updatedAt: time() - 50,
        );
        $this->sessionRepository->save($session);

        $tokens = $this->authService->login($session, 'DEV@example.com', 'secret');

        self::assertNotNull($tokens);
        self::assertSame('existing-session', $tokens->accessToken);
        self::assertNotSame('', $tokens->refreshToken);

        $updatedSession = $this->sessionRepository->findById('existing-session');
        self::assertNotNull($updatedSession);
        self::assertSame(101, $updatedSession->userId);

        $payload = json_decode($updatedSession->payload, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertSame($tokens->refreshToken, $payload['refreshToken']);
        self::assertSame('127.0.0.1', $payload['ip']);
    }

    public function testLoginReturnsNullForInvalidPassword(): void
    {
        $user = new AuthUser(
            id: 101,
            email: 'dev@example.com',
            passwordHash: password_hash('secret', PASSWORD_DEFAULT),
        );
        $this->userRepository->users[$user->email] = $user;

        $session = new Session(
            id: 'existing-session',
            userId: null,
            payload: '{}',
            expiresAt: time() + 100,
            createdAt: time() - 100,
            updatedAt: time() - 50,
        );

        $tokens = $this->authService->login($session, 'dev@example.com', 'wrong-password');

        self::assertNull($tokens);
    }

    public function testRefreshRotatesRefreshTokenAndDeletesAnonymousCurrentSession(): void
    {
        $authenticatedSession = new Session(
            id: 'authenticated-session',
            userId: 501,
            payload: '{"refreshToken":"refresh-1","ip":"127.0.0.1"}',
            expiresAt: time() + 600,
            createdAt: time() - 100,
            updatedAt: time() - 50,
        );
        $anonymousCurrentSession = new Session(
            id: 'anonymous-session',
            userId: null,
            payload: '{"ip":"127.0.0.1"}',
            expiresAt: time() + 600,
            createdAt: time() - 50,
            updatedAt: time() - 25,
        );

        $this->sessionRepository->save($authenticatedSession);
        $this->sessionRepository->save($anonymousCurrentSession);
        $this->refreshTokenSessionRepository->sessions['refresh-1'] = $authenticatedSession;

        $tokens = $this->authService->refresh($anonymousCurrentSession, 'refresh-1');

        self::assertNotNull($tokens);
        self::assertSame('authenticated-session', $tokens->accessToken);
        self::assertNotSame('refresh-1', $tokens->refreshToken);
        self::assertNull($this->sessionRepository->findById('anonymous-session'));

        $updatedSession = $this->sessionRepository->findById('authenticated-session');
        self::assertNotNull($updatedSession);
        $payload = json_decode($updatedSession->payload, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertSame($tokens->refreshToken, $payload['refreshToken']);
    }

    public function testLogoutDeletesCurrentSession(): void
    {
        $session = new Session(
            id: 'session-to-delete',
            userId: 12,
            payload: '{}',
            expiresAt: time() + 600,
            createdAt: time() - 100,
            updatedAt: time() - 50,
        );
        $this->sessionRepository->save($session);

        $this->authService->logout($session);

        self::assertNull($this->sessionRepository->findById('session-to-delete'));
    }
}

final class InMemoryAuthUserRepository implements AuthUserRepository
{
    /** @var array<string, AuthUser> */
    public array $users = [];

    public function findByEmail(string $email): ?AuthUser
    {
        return $this->users[$email] ?? null;
    }
}

final class InMemoryRefreshTokenSessionRepository implements RefreshTokenSessionRepository
{
    /** @var array<string, Session> */
    public array $sessions = [];

    public function findByRefreshToken(string $refreshToken): ?Session
    {
        return $this->sessions[$refreshToken] ?? null;
    }
}

final class InMemorySessionRepository implements SessionRepository
{
    /** @var array<string, Session> */
    private array $sessions = [];

    public function findById(string $id): ?Session
    {
        return $this->sessions[$id] ?? null;
    }

    public function findByUserId(int $userId): array
    {
        return array_values(array_filter(
            $this->sessions,
            static fn(Session $session): bool => $session->userId === $userId,
        ));
    }

    public function findAll(): array
    {
        return array_values($this->sessions);
    }

    public function save(Session $session): void
    {
        $this->sessions[$session->id] = $session;
    }

    public function delete(string $id): void
    {
        unset($this->sessions[$id]);
    }

    public function deleteExpired(): void
    {
        $now = time();

        foreach ($this->sessions as $id => $session) {
            if ($session->expiresAt < $now) {
                unset($this->sessions[$id]);
            }
        }
    }
}

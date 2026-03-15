<?php

declare(strict_types=1);

namespace App\Examples\Auth\Domain;

use App\Capabilities\Session\Domain\Session;
use App\Capabilities\Session\Domain\SessionConfig;
use App\Capabilities\Session\Domain\SessionRepository;
use App\Capabilities\Session\Domain\SessionService;

final readonly class AuthService
{
    public function __construct(
        private AuthUserRepository $userRepository,
        private RefreshTokenSessionRepository $refreshTokenSessionRepository,
        private SessionRepository $sessionRepository,
        private SessionService $sessionService,
        private SessionConfig $sessionConfig,
    ) {}

    public function login(Session $session, string $email, string $password): ?AuthTokens
    {
        $user = $this->userRepository->findByEmail($this->normalizeEmail($email));
        if ($user === null || !password_verify($password, $user->passwordHash)) {
            return null;
        }

        $refreshToken = $this->generateRefreshToken();
        $updatedSession = $this->withAuthState($session, $user->id, $refreshToken);
        $this->sessionRepository->save($updatedSession);

        return $this->createTokens($updatedSession->id, $refreshToken, $updatedSession->expiresAt);
    }

    public function refresh(?Session $currentSession, string $refreshToken): ?AuthTokens
    {
        $session = $this->refreshTokenSessionRepository->findByRefreshToken($refreshToken);
        if ($session === null || $session->isExpired() || $session->userId === null) {
            return null;
        }

        $rotatedRefreshToken = $this->generateRefreshToken();
        $expiresAt = $this->calculateExpiresAt();
        $refreshedSession = new Session(
            id: $session->id,
            userId: $session->userId,
            payload: $this->encodePayload($this->mergePayload($session->payload, $rotatedRefreshToken)),
            expiresAt: $expiresAt,
            createdAt: $session->createdAt,
            updatedAt: time(),
        );

        $this->sessionRepository->save($refreshedSession);

        if ($currentSession !== null && $currentSession->id !== $refreshedSession->id && $currentSession->userId === null) {
            $this->sessionService->deleteSession($currentSession->id);
        }

        return $this->createTokens($refreshedSession->id, $rotatedRefreshToken, $refreshedSession->expiresAt);
    }

    public function logout(?Session $session): void
    {
        if ($session === null) {
            return;
        }

        $this->sessionService->deleteSession($session->id);
    }

    private function withAuthState(Session $session, int $userId, string $refreshToken): Session
    {
        return new Session(
            id: $session->id,
            userId: $userId,
            payload: $this->encodePayload($this->mergePayload($session->payload, $refreshToken)),
            expiresAt: $this->calculateExpiresAt(),
            createdAt: $session->createdAt,
            updatedAt: time(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function mergePayload(string $payload, string $refreshToken): array
    {
        $decodedPayload = json_decode($payload, true);
        /** @var array<string, mixed> $sessionPayload */
        $sessionPayload = \is_array($decodedPayload) ? $decodedPayload : [];

        $sessionPayload['refreshToken'] = $refreshToken;

        return $sessionPayload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encodePayload(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    private function calculateExpiresAt(): int
    {
        $now = time();
        $expiresAt = $now + $this->sessionConfig->sessionTtl;

        if ($expiresAt < 0 || $this->sessionConfig->sessionTtl === PHP_INT_MAX) {
            return PHP_INT_MAX;
        }

        return $expiresAt;
    }

    private function createTokens(string $accessToken, string $refreshToken, int $expiresAt): AuthTokens
    {
        return new AuthTokens(
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            expiresIn: max(0, $expiresAt - time()),
        );
    }

    private function generateRefreshToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}

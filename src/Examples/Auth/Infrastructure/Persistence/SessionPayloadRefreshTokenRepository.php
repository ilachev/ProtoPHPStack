<?php

declare(strict_types=1);

namespace App\Examples\Auth\Infrastructure\Persistence;

use App\Capabilities\Session\Domain\Session;
use App\Capabilities\Session\Domain\SessionRepository;
use App\Examples\Auth\Domain\RefreshTokenSessionRepository;

final readonly class SessionPayloadRefreshTokenRepository implements RefreshTokenSessionRepository
{
    public function __construct(
        private SessionRepository $sessionRepository,
    ) {}

    public function findByRefreshToken(string $refreshToken): ?Session
    {
        foreach ($this->sessionRepository->findAll() as $session) {
            $decodedPayload = json_decode($session->payload, true);
            if (!\is_array($decodedPayload)) {
                continue;
            }

            if (($decodedPayload['refreshToken'] ?? null) === $refreshToken) {
                return $session;
            }
        }

        return null;
    }
}

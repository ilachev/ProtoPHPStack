<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain;

final readonly class AuthTokens
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public int $expiresIn,
    ) {}
}

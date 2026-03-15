<?php

declare(strict_types=1);

namespace App\Examples\Auth\Domain;

use App\Capabilities\Session\Domain\Session;

interface RefreshTokenSessionRepository
{
    public function findByRefreshToken(string $refreshToken): ?Session;
}

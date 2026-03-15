<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain;

use App\Modules\Session\Domain\Session;

interface RefreshTokenSessionRepository
{
    public function findByRefreshToken(string $refreshToken): ?Session;
}

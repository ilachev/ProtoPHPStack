<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain;

interface AuthUserRepository
{
    public function findByEmail(string $email): ?AuthUser;
}

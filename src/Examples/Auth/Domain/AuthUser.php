<?php

declare(strict_types=1);

namespace App\Examples\Auth\Domain;

final readonly class AuthUser
{
    public function __construct(
        public int $id,
        public string $email,
        public string $passwordHash,
    ) {}
}

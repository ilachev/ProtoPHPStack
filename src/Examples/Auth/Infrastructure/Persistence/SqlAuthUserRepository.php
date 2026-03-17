<?php

declare(strict_types=1);

namespace App\Examples\Auth\Infrastructure\Persistence;

use App\Examples\Auth\Domain\AuthUser;
use App\Examples\Auth\Domain\AuthUserRepository;
use App\Platform\Storage\Repository\AbstractRepository;

final class SqlAuthUserRepository extends AbstractRepository implements AuthUserRepository
{
    private const TABLE_NAME = 'users';

    public function findByEmail(string $email): ?AuthUser
    {
        $query = $this->query(self::TABLE_NAME)
            ->where('email', $email);

        return $this->fetchOne(AuthUser::class, $query);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Persistence;

use App\Infrastructure\Hydrator\Hydrator;
use App\Infrastructure\Storage\Query\QueryFactory;
use App\Infrastructure\Storage\Repository\AbstractRepository;
use App\Infrastructure\Storage\Storage;
use App\Modules\Auth\Domain\AuthUser;
use App\Modules\Auth\Domain\AuthUserRepository;

final class PostgreSQLAuthUserRepository extends AbstractRepository implements AuthUserRepository
{
    private const TABLE_NAME = 'users';

    public function __construct(
        Storage $storage,
        Hydrator $hydrator,
        QueryFactory $queryFactory,
    ) {
        parent::__construct($storage, $hydrator, $queryFactory);
    }

    public function findByEmail(string $email): ?AuthUser
    {
        $query = $this->query(self::TABLE_NAME)
            ->where('email', $email);

        return $this->fetchOne(AuthUser::class, $query);
    }
}

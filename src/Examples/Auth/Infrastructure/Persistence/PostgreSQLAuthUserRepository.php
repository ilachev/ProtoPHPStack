<?php

declare(strict_types=1);

namespace App\Examples\Auth\Infrastructure\Persistence;

use App\Examples\Auth\Domain\AuthUser;
use App\Examples\Auth\Domain\AuthUserRepository;
use App\Infrastructure\Hydrator\Hydrator;
use App\Platform\Storage\Query\QueryFactory;
use App\Platform\Storage\Repository\AbstractRepository;
use App\Platform\Storage\Storage;

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

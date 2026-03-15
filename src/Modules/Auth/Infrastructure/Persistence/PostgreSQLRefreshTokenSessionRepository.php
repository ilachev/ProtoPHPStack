<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Persistence;

use App\Infrastructure\Hydrator\Hydrator;
use App\Infrastructure\Storage\Query\QueryFactory;
use App\Infrastructure\Storage\Repository\AbstractRepository;
use App\Infrastructure\Storage\Storage;
use App\Modules\Auth\Domain\RefreshTokenSessionRepository;
use App\Modules\Session\Domain\Session;

final class PostgreSQLRefreshTokenSessionRepository extends AbstractRepository implements RefreshTokenSessionRepository
{
    private const TABLE_NAME = 'sessions';

    public function __construct(
        Storage $storage,
        Hydrator $hydrator,
        QueryFactory $queryFactory,
    ) {
        parent::__construct($storage, $hydrator, $queryFactory);
    }

    public function findByRefreshToken(string $refreshToken): ?Session
    {
        $query = $this->query(self::TABLE_NAME)
            ->where("payload->>'refreshToken'", $refreshToken);

        return $this->fetchOne(Session::class, $query);
    }
}

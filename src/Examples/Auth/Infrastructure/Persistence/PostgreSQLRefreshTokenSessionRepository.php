<?php

declare(strict_types=1);

namespace App\Examples\Auth\Infrastructure\Persistence;

use App\Capabilities\Session\Domain\Session;
use App\Examples\Auth\Domain\RefreshTokenSessionRepository;
use App\Infrastructure\Hydrator\Hydrator;
use App\Platform\Storage\Query\QueryFactory;
use App\Platform\Storage\Repository\AbstractRepository;
use App\Platform\Storage\Storage;

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

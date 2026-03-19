<?php

declare(strict_types=1);

namespace App\Capabilities\Session\Infrastructure\Persistence;

use App\Capabilities\Session\Domain\Session;
use App\Capabilities\Session\Domain\SessionRepository;
use App\Generated\Sql\Session\DeleteExpiredSessionsQuery;
use App\Generated\Sql\Session\FindAllSessionsQuery;
use App\Generated\Sql\Session\FindSessionByIdQuery;
use App\Generated\Sql\Session\FindSessionsByUserIdQuery;
use App\Generated\Sql\Session\SessionRow;
use App\Platform\Hydration\Hydrator;
use App\Platform\Storage\Query\QueryFactory;
use App\Platform\Storage\Repository\AbstractRepository;
use App\Platform\Storage\Sql\SqlExecutor;
use App\Platform\Storage\Storage;

final class SqlSessionRepository extends AbstractRepository implements SessionRepository
{
    private const TABLE_NAME = 'sessions';

    public function __construct(
        Storage $storage,
        Hydrator $hydrator,
        QueryFactory $queryBuilderFactory,
        private readonly SqlExecutor $sqlExecutor,
    ) {
        parent::__construct($storage, $hydrator, $queryBuilderFactory);
    }

    public function findById(string $id): ?Session
    {
        $row = $this->sqlExecutor->fetchOneAs(
            FindSessionByIdQuery::create(id: $id),
            SessionRow::class,
        );

        if ($row === null) {
            return null;
        }

        return $this->createSessionFromRow($row);
    }

    public function findByUserId(int $userId): array
    {
        $rows = $this->sqlExecutor->fetchAllAs(
            FindSessionsByUserIdQuery::create(user_id: $userId),
            SessionRow::class,
        );

        return array_map(
            fn(SessionRow $row): Session => $this->createSessionFromRow($row),
            $rows,
        );
    }

    public function findAll(): array
    {
        $rows = $this->sqlExecutor->fetchAllAs(
            FindAllSessionsQuery::create(),
            SessionRow::class,
        );

        return array_map(
            fn(SessionRow $row): Session => $this->createSessionFromRow($row),
            $rows,
        );
    }

    public function save(Session $session): void
    {
        $this->saveEntity($session, self::TABLE_NAME, 'id', $session->id);
    }

    public function delete(string $id): void
    {
        $this->deleteEntity(self::TABLE_NAME, 'id', $id);
    }

    public function deleteExpired(): void
    {
        $this->sqlExecutor->execute(
            DeleteExpiredSessionsQuery::create(now: time()),
        );
    }

    private function createSessionFromRow(SessionRow $row): Session
    {
        return new Session(
            id: $row->id,
            userId: $row->userId,
            payload: $row->payload,
            expiresAt: $row->expiresAt,
            createdAt: $row->createdAt,
            updatedAt: $row->updatedAt,
        );
    }
}

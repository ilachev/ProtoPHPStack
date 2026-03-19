<?php

declare(strict_types=1);

namespace App\Capabilities\Session\Infrastructure\Persistence;

use App\Capabilities\Session\Domain\Session;
use App\Capabilities\Session\Domain\SessionRepository;
use App\Generated\Sql\Session\DeleteExpiredSessionsParams;
use App\Generated\Sql\Session\FindSessionByIdParams;
use App\Generated\Sql\Session\FindSessionsByUserIdParams;
use App\Generated\Sql\Session\SessionQueries;
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
        private readonly SessionQueries $sessionQueries,
    ) {
        parent::__construct($storage, $hydrator, $queryBuilderFactory);
    }

    public function findById(string $id): ?Session
    {
        $row = $this->sqlExecutor->fetchOne(
            $this->sessionQueries->findSessionById(new FindSessionByIdParams($id)),
        );

        if ($row === null) {
            return null;
        }

        return $this->createSessionFromRow(
            SessionRow::fromDatabaseRow($row),
        );
    }

    public function findByUserId(int $userId): array
    {
        $rows = $this->sqlExecutor->fetchAll(
            $this->sessionQueries->findSessionsByUserId(new FindSessionsByUserIdParams($userId)),
        );

        return array_map(
            fn(array $row): Session => $this->createSessionFromRow(
                SessionRow::fromDatabaseRow($row),
            ),
            $rows,
        );
    }

    public function findAll(): array
    {
        $rows = $this->sqlExecutor->fetchAll(
            $this->sessionQueries->findAllSessions(),
        );

        return array_map(
            fn(array $row): Session => $this->createSessionFromRow(
                SessionRow::fromDatabaseRow($row),
            ),
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
            $this->sessionQueries->deleteExpiredSessions(
                new DeleteExpiredSessionsParams(time()),
            ),
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

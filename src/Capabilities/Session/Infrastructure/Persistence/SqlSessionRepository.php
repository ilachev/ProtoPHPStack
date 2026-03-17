<?php

declare(strict_types=1);

namespace App\Capabilities\Session\Infrastructure\Persistence;

use App\Capabilities\Session\Domain\Session;
use App\Capabilities\Session\Domain\SessionRepository;
use App\Platform\Storage\Repository\AbstractRepository;

final class SqlSessionRepository extends AbstractRepository implements SessionRepository
{
    private const TABLE_NAME = 'sessions';

    public function findById(string $id): ?Session
    {
        $query = $this->query(self::TABLE_NAME)
            ->where('id', $id);

        return $this->fetchOne(Session::class, $query);
    }

    public function findByUserId(int $userId): array
    {
        $query = $this->query(self::TABLE_NAME)
            ->where('user_id', $userId);

        return $this->fetchAll(Session::class, $query);
    }

    public function findAll(): array
    {
        $query = $this->query(self::TABLE_NAME);

        return $this->fetchAll(Session::class, $query);
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
        $deleteQuery = $this->query(self::TABLE_NAME)
            ->where('expires_at', time(), '<');

        [$sql, $params] = $deleteQuery->buildDeleteQuery();
        /** @var array<string, scalar|null> $castParams */
        $castParams = $params;
        $this->storage->execute($sql, $castParams);
    }
}

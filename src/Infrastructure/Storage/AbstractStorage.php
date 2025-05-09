<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

abstract class AbstractStorage implements Storage
{
    protected \PDO $connection;

    /**
     * @throws \Throwable
     */
    final public function transaction(callable $callback): mixed
    {
        $this->connection->beginTransaction();

        try {
            $result = $callback();
            $this->connection->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->connection->rollBack();

            throw $e;
        }
    }

    /**
     * @throws StorageException
     */
    final public function lastInsertId(): string
    {
        $id = $this->connection->lastInsertId();

        if ($id === false) {
            throw new StorageException('Failed to get last insert ID');
        }

        return $id;
    }
}

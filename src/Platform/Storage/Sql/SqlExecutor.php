<?php

declare(strict_types=1);

namespace App\Platform\Storage\Sql;

use App\Platform\Storage\Storage;

final readonly class SqlExecutor
{
    public function __construct(
        private Storage $storage,
    ) {}

    /**
     * @return array<string, scalar|null>|null
     */
    public function fetchOne(ExecutableQuery $query): ?array
    {
        $rows = $this->storage->query($query->sql(), $query->params());

        return $rows[0] ?? null;
    }

    /**
     * @template T of DatabaseRow
     * @param OneRowQuery<T> $query
     * @return T|null
     */
    public function fetchOneRow(OneRowQuery $query): ?DatabaseRow
    {
        $row = $this->fetchOne($query);
        if ($row === null) {
            return null;
        }

        /** @var class-string<T> $rowClass */
        $rowClass = $query->rowClass();

        /** @var T */
        return $rowClass::fromDatabaseRow($row);
    }

    /**
     * @return list<array<string, scalar|null>>
     */
    public function fetchAll(ExecutableQuery $query): array
    {
        return $this->storage->query($query->sql(), $query->params());
    }

    /**
     * @template T of DatabaseRow
     * @param ManyRowsQuery<T> $query
     * @return list<T>
     */
    public function fetchAllRows(ManyRowsQuery $query): array
    {
        /** @var class-string<T> $rowClass */
        $rowClass = $query->rowClass();

        return array_map(
            /**
             * @return T
             */
            static fn(array $row): DatabaseRow => $rowClass::fromDatabaseRow($row),
            $this->fetchAll($query),
        );
    }

    public function execute(ExecutableQuery $query): bool
    {
        return $this->storage->execute($query->sql(), $query->params());
    }
}

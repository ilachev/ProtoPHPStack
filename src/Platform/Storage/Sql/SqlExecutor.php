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
     * @return list<array<string, scalar|null>>
     */
    public function fetchAll(ExecutableQuery $query): array
    {
        return $this->storage->query($query->sql(), $query->params());
    }

    public function execute(ExecutableQuery $query): bool
    {
        return $this->storage->execute($query->sql(), $query->params());
    }
}

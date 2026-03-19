<?php

declare(strict_types=1);

namespace App\Platform\Storage\Sql;

/**
 * @template TRow of DatabaseRow
 * @template TParams of array<string, scalar|null>
 * @extends ExecutableQuery<TParams>
 */
interface RowReturningQuery extends ExecutableQuery
{
    /**
     * @return class-string<TRow>
     */
    public function rowClass(): string;
}

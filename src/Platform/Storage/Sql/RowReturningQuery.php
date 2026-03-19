<?php

declare(strict_types=1);

namespace App\Platform\Storage\Sql;

/**
 * @template T of DatabaseRow
 */
interface RowReturningQuery extends ExecutableQuery
{
    /**
     * @return class-string<T>
     */
    public function rowClass(): string;
}

<?php

declare(strict_types=1);

namespace App\Platform\Storage\Sql;

/**
 * @template TRow of DatabaseRow
 * @template TParams of array<string, scalar|null>
 * @extends RowReturningQuery<TRow, TParams>
 */
interface ManyRowsQuery extends RowReturningQuery {}

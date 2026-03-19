<?php

declare(strict_types=1);

namespace App\Platform\Storage\Sql;

/**
 * @template T of DatabaseRow
 * @extends RowReturningQuery<T>
 */
interface ManyRowsQuery extends RowReturningQuery {}

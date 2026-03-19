<?php

declare(strict_types=1);

namespace App\Platform\Storage\Sql;

/**
 * @template TRowData of array<string, scalar|null>
 */
interface DatabaseRow
{
    /**
     * @param TRowData $row
     */
    public static function fromDatabaseRow(array $row): static;
}

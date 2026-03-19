<?php

declare(strict_types=1);

namespace App\Platform\Storage\Sql;

interface DatabaseRow
{
    /**
     * @param array<string, scalar|null> $row
     */
    public static function fromDatabaseRow(array $row): static;
}

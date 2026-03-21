<?php

declare(strict_types=1);

namespace SqlGen\Schema;

final readonly class SchemaForeignKeyConstraintDefinition
{
    /**
     * @param list<string> $columns
     * @param list<string> $referencedColumns
     */
    public function __construct(
        public array $columns,
        public string $referencedTable,
        public array $referencedColumns,
    ) {}
}

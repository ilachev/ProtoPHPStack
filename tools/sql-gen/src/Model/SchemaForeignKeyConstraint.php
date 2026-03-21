<?php

declare(strict_types=1);

namespace SqlGen\Model;

final readonly class SchemaForeignKeyConstraint
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

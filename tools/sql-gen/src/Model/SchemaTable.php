<?php

declare(strict_types=1);

namespace SqlGen\Model;

final readonly class SchemaTable
{
    /**
     * @param array<string, SchemaColumn> $columns
     * @param list<string> $primaryKeyColumns
     * @param list<SchemaUniqueConstraint> $uniqueConstraints
     * @param list<SchemaForeignKeyConstraint> $foreignKeys
     */
    public function __construct(
        public string $name,
        public array $columns,
        public array $primaryKeyColumns = [],
        public array $uniqueConstraints = [],
        public array $foreignKeys = [],
    ) {}

    public function getColumn(string $name): ?SchemaColumn
    {
        return $this->columns[$name] ?? null;
    }
}

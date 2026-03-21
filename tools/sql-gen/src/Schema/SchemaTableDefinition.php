<?php

declare(strict_types=1);

namespace SqlGen\Schema;

final readonly class SchemaTableDefinition
{
    /**
     * @param list<SchemaColumnDefinition> $columns
     * @param list<string> $primaryKeyColumns
     * @param list<SchemaUniqueConstraintDefinition> $uniqueConstraints
     * @param list<SchemaForeignKeyConstraintDefinition> $foreignKeys
     */
    public function __construct(
        public string $name,
        public array $columns,
        public array $primaryKeyColumns = [],
        public array $uniqueConstraints = [],
        public array $foreignKeys = [],
    ) {}
}

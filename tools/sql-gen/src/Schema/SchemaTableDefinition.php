<?php

declare(strict_types=1);

namespace SqlGen\Schema;

final readonly class SchemaTableDefinition
{
    /**
     * @param list<SchemaColumnDefinition> $columns
     */
    public function __construct(
        public string $name,
        public array $columns,
    ) {}
}

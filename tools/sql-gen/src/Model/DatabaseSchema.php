<?php

declare(strict_types=1);

namespace SqlGen\Model;

final readonly class DatabaseSchema
{
    /**
     * @param array<string, SchemaTable> $tables
     */
    public function __construct(
        public array $tables,
    ) {}

    public function getTable(string $name): ?SchemaTable
    {
        return $this->tables[$name] ?? null;
    }
}

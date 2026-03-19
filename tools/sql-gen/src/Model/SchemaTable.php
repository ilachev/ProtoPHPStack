<?php

declare(strict_types=1);

namespace SqlGen\Model;

final readonly class SchemaTable
{
    /**
     * @param array<string, SchemaColumn> $columns
     */
    public function __construct(
        public string $name,
        public array $columns,
    ) {}

    public function getColumn(string $name): ?SchemaColumn
    {
        return $this->columns[$name] ?? null;
    }
}

<?php

declare(strict_types=1);

namespace SqlGen\Schema;

use SqlGen\Model\SchemaTable;

final readonly class StatementVisibleTable
{
    public function __construct(
        public SchemaTable $table,
        public ?string $alias = null,
    ) {}

    public function matchesQualifier(string $qualifier): bool
    {
        $normalizedQualifier = strtolower($qualifier);

        if ($this->table->name === $normalizedQualifier) {
            return true;
        }

        return $this->alias !== null && strtolower($this->alias) === $normalizedQualifier;
    }

    public function resolveColumn(string $columnName): ?ResolvedSchemaColumn
    {
        $column = $this->table->getColumn($columnName);
        if ($column === null) {
            return null;
        }

        return new ResolvedSchemaColumn($this->table, $columnName, $column);
    }

    /**
     * @return list<ResolvedSchemaColumn>
     */
    public function expandColumns(): array
    {
        $columns = [];

        foreach (array_keys($this->table->columns) as $columnName) {
            $column = $this->table->getColumn($columnName);
            if ($column === null) {
                continue;
            }

            $columns[] = new ResolvedSchemaColumn($this->table, $columnName, $column);
        }

        return $columns;
    }
}

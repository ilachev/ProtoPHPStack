<?php

declare(strict_types=1);

namespace SqlGen\Schema;

use SqlGen\Model\SchemaTable;

final readonly class StatementTableMap
{
    /**
     * @param array<string, SchemaTable> $references
     * @param list<SchemaTable> $tables
     */
    public function __construct(
        private array $references,
        private array $tables,
        private ?SchemaTable $primaryTable,
        private string $queryName,
    ) {}

    public function resolveColumn(?string $qualifier, string $columnName): ResolvedSchemaColumn
    {
        if (is_string($qualifier) && $qualifier !== '') {
            $table = $this->references[$qualifier] ?? null;
            if ($table === null) {
                throw new \RuntimeException(
                    "Unknown table or alias {$qualifier} in query {$this->queryName}",
                );
            }

            $column = $table->getColumn($columnName);
            if ($column === null) {
                throw new \RuntimeException(
                    "Column {$columnName} was not found in schema table {$table->name} for query {$this->queryName}",
                );
            }

            return new ResolvedSchemaColumn($table, $columnName, $column);
        }

        $resolved = [];

        foreach ($this->tables as $table) {
            $column = $table->getColumn($columnName);
            if ($column === null) {
                continue;
            }

            $resolved[] = new ResolvedSchemaColumn($table, $columnName, $column);
        }

        if ($resolved === []) {
            throw new \RuntimeException(
                "Column {$columnName} was not found in visible schema tables for query {$this->queryName}",
            );
        }

        if (count($resolved) > 1) {
            throw new \RuntimeException(
                "Column {$columnName} is ambiguous in query {$this->queryName}; qualify it with a table or alias",
            );
        }

        return $resolved[0];
    }

    /**
     * @return list<ResolvedSchemaColumn>
     */
    public function expandWildcard(?string $qualifier): array
    {
        $table = null;

        if (is_string($qualifier) && $qualifier !== '') {
            $table = $this->references[$qualifier] ?? null;
            if ($table === null) {
                throw new \RuntimeException(
                    "Unknown table or alias {$qualifier} in query {$this->queryName}",
                );
            }
        } elseif ($this->primaryTable !== null) {
            $table = $this->primaryTable;
        } elseif (count($this->tables) === 1) {
            $table = $this->tables[0];
        }

        if ($table === null) {
            throw new \RuntimeException(
                "Wildcard column selection is ambiguous in query {$this->queryName}",
            );
        }

        $columns = [];

        foreach (array_keys($table->columns) as $columnName) {
            $column = $table->getColumn($columnName);
            if ($column === null) {
                continue;
            }

            $columns[] = new ResolvedSchemaColumn($table, $columnName, $column);
        }

        return $columns;
    }
}

<?php

declare(strict_types=1);

namespace SqlGen\Schema;

final readonly class StatementTableMap
{
    /**
     * @param list<StatementVisibleTable> $visibleTables
     */
    public function __construct(
        private array $visibleTables,
        private ?StatementVisibleTable $primaryTable,
        private string $queryName,
    ) {}

    public function resolveColumn(?string $qualifier, string $columnName): ResolvedSchemaColumn
    {
        if (is_string($qualifier) && $qualifier !== '') {
            $table = $this->resolveQualifiedTable($qualifier);
            if ($table === null) {
                throw new \RuntimeException(
                    "Unknown table or alias {$qualifier} in query {$this->queryName}",
                );
            }

            $column = $table->resolveColumn($columnName);
            if ($column === null) {
                throw new \RuntimeException(
                    "Column {$columnName} was not found in schema table {$table->table->name} for query {$this->queryName}",
                );
            }

            return $column;
        }

        $resolved = [];

        foreach ($this->visibleTables as $table) {
            $column = $table->resolveColumn($columnName);
            if ($column === null) {
                continue;
            }

            $resolved[] = $column;
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
            $table = $this->resolveQualifiedTable($qualifier);
            if ($table === null) {
                throw new \RuntimeException(
                    "Unknown table or alias {$qualifier} in query {$this->queryName}",
                );
            }
        } elseif ($this->primaryTable !== null) {
            $table = $this->primaryTable;
        } elseif (count($this->visibleTables) === 1) {
            $table = $this->visibleTables[0];
        }

        if ($table === null) {
            throw new \RuntimeException(
                "Wildcard column selection is ambiguous in query {$this->queryName}",
            );
        }

        return $table->expandColumns();
    }

    private function resolveQualifiedTable(string $qualifier): ?StatementVisibleTable
    {
        foreach ($this->visibleTables as $table) {
            if ($table->matchesQualifier($qualifier)) {
                return $table;
            }
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace SqlGen\Schema;

use SqlGen\Model\DatabaseSchema;
use SqlGen\Model\SchemaTable;
use SqlGen\Model\SqlStatement;

final class StatementTableMapResolver
{
    /**
     * @var list<string>
     */
    private const RESERVED_ALIASES = [
        'where',
        'join',
        'left',
        'right',
        'inner',
        'outer',
        'full',
        'cross',
        'on',
        'order',
        'group',
        'limit',
        'offset',
        'returning',
        'values',
        'set',
    ];

    public function resolve(SqlStatement $statement, DatabaseSchema $schema): StatementTableMap
    {
        $references = [];
        $tables = [];
        $primaryTable = $this->resolvePrimaryTable($statement, $schema);

        if ($primaryTable !== null) {
            $references[$primaryTable->name] = $primaryTable;
            $tables[] = $primaryTable;
        }

        preg_match_all(
            '/\b(?:FROM|JOIN)\s+(?<table>[a-zA-Z_][a-zA-Z0-9_]*)(?:\s+(?:AS\s+)?(?<alias>[a-zA-Z_][a-zA-Z0-9_]*))?/i',
            $statement->sql,
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            $tableName = strtolower($match['table']);
            $table = $schema->getTable($tableName);
            if ($table === null) {
                throw new \RuntimeException("Table {$tableName} was not found in schema for query {$statement->name}");
            }

            $alias = $this->normalizeAlias($match['alias'] ?? null);
            $references[$table->name] = $table;

            if ($alias !== null) {
                $references[$alias] = $table;
            }

            if (!$this->containsTable($tables, $table)) {
                $tables[] = $table;
            }
        }

        if ($tables === []) {
            throw new \RuntimeException("Unable to resolve table name for query {$statement->name}");
        }

        return new StatementTableMap(
            references: $references,
            tables: $tables,
            primaryTable: $primaryTable ?? $tables[0],
            queryName: $statement->name,
        );
    }

    private function resolvePrimaryTable(SqlStatement $statement, DatabaseSchema $schema): ?SchemaTable
    {
        $tableName = null;

        if (preg_match('/\bINSERT\s+INTO\s+(?<table>[a-zA-Z_][a-zA-Z0-9_]*)\b/i', $statement->sql, $matches)) {
            $tableName = strtolower($matches['table']);
        } elseif (preg_match('/\bDELETE\s+FROM\s+(?<table>[a-zA-Z_][a-zA-Z0-9_]*)\b/i', $statement->sql, $matches)) {
            $tableName = strtolower($matches['table']);
        } elseif (preg_match('/\bUPDATE\s+(?<table>[a-zA-Z_][a-zA-Z0-9_]*)\b/i', $statement->sql, $matches)) {
            $tableName = strtolower($matches['table']);
        }

        if ($tableName === null) {
            return null;
        }

        $table = $schema->getTable($tableName);
        if ($table === null) {
            throw new \RuntimeException("Table {$tableName} was not found in schema for query {$statement->name}");
        }

        return $table;
    }

    private function normalizeAlias(?string $alias): ?string
    {
        if (!is_string($alias) || $alias === '') {
            return null;
        }

        $normalized = strtolower($alias);

        if (in_array($normalized, self::RESERVED_ALIASES, true)) {
            return null;
        }

        return $normalized;
    }

    /**
     * @param list<SchemaTable> $tables
     */
    private function containsTable(array $tables, SchemaTable $candidate): bool
    {
        foreach ($tables as $table) {
            if ($table->name === $candidate->name) {
                return true;
            }
        }

        return false;
    }
}

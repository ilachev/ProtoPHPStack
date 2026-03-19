<?php

declare(strict_types=1);

namespace SqlGen\Schema;

use SqlGen\Model\DatabaseSchema;
use SqlGen\Model\RowField;
use SqlGen\Model\SchemaTable;
use SqlGen\Model\SqlResultKind;
use SqlGen\Model\SqlStatement;

final class StatementRowResolver
{
    /**
     * @return list<RowField>
     */
    public function resolve(SqlStatement $statement, DatabaseSchema $schema): array
    {
        if ($statement->resultKind === SqlResultKind::Exec) {
            return [];
        }

        $table = $this->resolveTable($statement, $schema);
        $columns = $this->resolveSelectedColumns($statement, $table);

        $fields = [];

        foreach ($columns as $column) {
            $schemaColumn = $table->getColumn($column['source']);
            if ($schemaColumn === null) {
                throw new \RuntimeException(
                    "Column {$column['source']} was not found in schema table {$table->name} for query {$statement->name}",
                );
            }

            $fields[] = new RowField(
                sourceColumnName: $column['source'],
                resultColumnName: $column['result'],
                propertyName: $this->snakeToCamel($column['result']),
                phpType: $schemaColumn->phpType,
                nullable: $schemaColumn->nullable,
            );
        }

        return $fields;
    }

    private function resolveTable(SqlStatement $statement, DatabaseSchema $schema): SchemaTable
    {
        if (preg_match('/\bINSERT\s+INTO\s+(?<table>[a-zA-Z_][a-zA-Z0-9_]*)\b/i', $statement->sql, $matches)) {
            $tableName = $matches['table'];
        } elseif (preg_match('/\bDELETE\s+FROM\s+(?<table>[a-zA-Z_][a-zA-Z0-9_]*)\b/i', $statement->sql, $matches)) {
            $tableName = $matches['table'];
        } elseif (preg_match('/\bUPDATE\s+(?<table>[a-zA-Z_][a-zA-Z0-9_]*)\b/i', $statement->sql, $matches)) {
            $tableName = $matches['table'];
        } elseif (preg_match('/\bFROM\s+(?<table>[a-zA-Z_][a-zA-Z0-9_]*)\b/i', $statement->sql, $matches)) {
            $tableName = $matches['table'];
        } else {
            throw new \RuntimeException("Unable to resolve table name for query {$statement->name}");
        }

        $table = $schema->getTable($tableName);
        if ($table === null) {
            throw new \RuntimeException("Table {$tableName} was not found in schema for query {$statement->name}");
        }

        return $table;
    }

    /**
     * @return list<array{source: string, result: string}>
     */
    private function resolveSelectedColumns(SqlStatement $statement, SchemaTable $table): array
    {
        if (preg_match('/\bSELECT\s+(?<columns>.*?)\s+FROM\b/is', $statement->sql, $matches)) {
            $columnsExpression = trim($matches['columns']);
        } elseif (preg_match('/\bRETURNING\s+(?<columns>.*?)(?:;|$)/is', $statement->sql, $matches)) {
            $columnsExpression = trim($matches['columns']);
        } else {
            throw new \RuntimeException("Unable to resolve row columns for query {$statement->name}");
        }

        if ($columnsExpression === '*') {
            return array_map(
                static fn(string $columnName): array => [
                    'source' => $columnName,
                    'result' => $columnName,
                ],
                array_keys($table->columns),
            );
        }

        $parts = preg_split('/\s*,\s*/', $columnsExpression);
        if (!\is_array($parts) || $parts === []) {
            throw new \RuntimeException("Unable to parse row columns for query {$statement->name}");
        }

        $columns = [];

        foreach ($parts as $part) {
            $column = trim($part);
            if ($column === '') {
                continue;
            }

            if (preg_match('/^(?:(?<table>[a-zA-Z_][a-zA-Z0-9_]*)\.)?(?<column>[a-zA-Z_][a-zA-Z0-9_]*)(?:\s+AS\s+(?<alias>[a-zA-Z_][a-zA-Z0-9_]*))?$/i', $column, $named)) {
                $sourceColumn = $named['column'];
                $alias = $named['alias'] ?? null;
                $resultColumn = is_string($alias) ? $alias : $sourceColumn;

                $columns[] = [
                    'source' => $sourceColumn,
                    'result' => $resultColumn,
                ];
                continue;
            }

            throw new \RuntimeException("Unsupported selected column expression '{$column}' in query {$statement->name}");
        }

        return $columns;
    }

    private function snakeToCamel(string $value): string
    {
        return lcfirst(str_replace('_', '', ucwords($value, '_')));
    }
}

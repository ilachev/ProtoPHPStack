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
        $columns = $this->resolveSelectedColumns($statement);

        $fields = [];

        foreach ($columns as $columnName) {
            $schemaColumn = $table->getColumn($columnName);
            if ($schemaColumn === null) {
                throw new \RuntimeException(
                    "Column {$columnName} was not found in schema table {$table->name} for query {$statement->name}",
                );
            }

            $fields[] = new RowField(
                columnName: $columnName,
                propertyName: $this->snakeToCamel($columnName),
                phpType: $schemaColumn->phpType,
                nullable: $schemaColumn->nullable,
            );
        }

        return $fields;
    }

    private function resolveTable(SqlStatement $statement, DatabaseSchema $schema): SchemaTable
    {
        if (!preg_match('/\bFROM\s+(?<table>[a-zA-Z_][a-zA-Z0-9_]*)\b/i', $statement->sql, $matches)) {
            throw new \RuntimeException("Unable to resolve table name for query {$statement->name}");
        }

        $tableName = $matches['table'];
        $table = $schema->getTable($tableName);
        if ($table === null) {
            throw new \RuntimeException("Table {$tableName} was not found in schema for query {$statement->name}");
        }

        return $table;
    }

    /**
     * @return list<string>
     */
    private function resolveSelectedColumns(SqlStatement $statement): array
    {
        if (!preg_match('/\bSELECT\s+(?<columns>.*?)\s+FROM\b/is', $statement->sql, $matches)) {
            throw new \RuntimeException("Unable to resolve SELECT columns for query {$statement->name}");
        }

        $columnsExpression = trim($matches['columns']);

        if ($columnsExpression === '*') {
            throw new \RuntimeException("SELECT * is not supported for typed row generation in query {$statement->name}");
        }

        $parts = preg_split('/\s*,\s*/', $columnsExpression);
        if (!\is_array($parts) || $parts === []) {
            throw new \RuntimeException("Unable to parse selected columns for query {$statement->name}");
        }

        $columns = [];

        foreach ($parts as $part) {
            $column = trim($part);
            if ($column === '') {
                continue;
            }

            if (preg_match('/^(?<table>[a-zA-Z_][a-zA-Z0-9_]*)\.(?<column>[a-zA-Z_][a-zA-Z0-9_]*)$/', $column, $qualified)) {
                $columns[] = $qualified['column'];
                continue;
            }

            if (preg_match('/^(?<column>[a-zA-Z_][a-zA-Z0-9_]*)$/', $column, $simple)) {
                $columns[] = $simple['column'];
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

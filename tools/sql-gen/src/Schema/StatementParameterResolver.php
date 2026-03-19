<?php

declare(strict_types=1);

namespace SqlGen\Schema;

use SqlGen\Model\DatabaseSchema;
use SqlGen\Model\ResolvedSqlParameter;
use SqlGen\Model\SchemaTable;
use SqlGen\Model\SqlStatement;

final class StatementParameterResolver
{
    /**
     * @return list<ResolvedSqlParameter>
     */
    public function resolve(SqlStatement $statement, DatabaseSchema $schema): array
    {
        $table = $this->resolveTable($statement, $schema);
        $resolvedByName = [];

        foreach ($this->extractColumnComparisons($statement->sql) as $comparison) {
            $column = $table->getColumn($comparison['column']);
            if ($column === null) {
                throw new \RuntimeException(
                    "Column {$comparison['column']} was not found in schema table {$table->name} for query {$statement->name}",
                );
            }

            $resolvedByName[$comparison['param']] = new ResolvedSqlParameter(
                name: $comparison['param'],
                propertyName: $this->snakeToCamel($comparison['param']),
                sqlType: $column->sqlType,
                phpType: $column->phpType,
            );
        }

        $parameters = [];

        foreach ($statement->parameters as $parameter) {
            $resolved = $resolvedByName[$parameter->name] ?? null;
            if ($resolved === null) {
                throw new \RuntimeException(
                    "Unable to resolve SQL parameter type for {$parameter->name} in query {$statement->name}",
                );
            }

            $parameters[] = $resolved;
        }

        return $parameters;
    }

    private function resolveTable(SqlStatement $statement, DatabaseSchema $schema): SchemaTable
    {
        if (preg_match('/\bFROM\s+(?<table>[a-zA-Z_][a-zA-Z0-9_]*)\b/i', $statement->sql, $matches)) {
            $tableName = $matches['table'];
        } elseif (preg_match('/\bUPDATE\s+(?<table>[a-zA-Z_][a-zA-Z0-9_]*)\b/i', $statement->sql, $matches)) {
            $tableName = $matches['table'];
        } elseif (preg_match('/\bINSERT\s+INTO\s+(?<table>[a-zA-Z_][a-zA-Z0-9_]*)\b/i', $statement->sql, $matches)) {
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
     * @return list<array{column: string, param: string}>
     */
    private function extractColumnComparisons(string $sql): array
    {
        preg_match_all(
            '/(?:(?<left_table>[a-zA-Z_][a-zA-Z0-9_]*)\.)?(?<left_column>[a-zA-Z_][a-zA-Z0-9_]*)\s*(?:=|<|>|<=|>=)\s*:(?<right_param>[a-zA-Z_][a-zA-Z0-9_]*)|:(?<left_param>[a-zA-Z_][a-zA-Z0-9_]*)\s*(?:=|<|>|<=|>=)\s*(?:(?<right_table>[a-zA-Z_][a-zA-Z0-9_]*)\.)?(?<right_column>[a-zA-Z_][a-zA-Z0-9_]*)/i',
            $sql,
            $matches,
            PREG_SET_ORDER,
        );

        $comparisons = [];

        foreach ($matches as $match) {
            $column = $match['left_column'] ?: $match['right_column'];
            $param = $match['right_param'] ?: $match['left_param'];

            if (!is_string($column) || $column === '' || !is_string($param) || $param === '') {
                continue;
            }

            $comparisons[] = [
                'column' => $column,
                'param' => $param,
            ];
        }

        return $comparisons;
    }

    private function snakeToCamel(string $value): string
    {
        return lcfirst(str_replace('_', '', ucwords($value, '_')));
    }
}

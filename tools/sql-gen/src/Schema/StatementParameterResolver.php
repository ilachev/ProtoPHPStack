<?php

declare(strict_types=1);

namespace SqlGen\Schema;

use SqlGen\Model\DatabaseSchema;
use SqlGen\Model\ResolvedSqlParameter;
use SqlGen\Model\SqlStatement;

final class StatementParameterResolver
{
    private StatementTableMapResolver $tableMapResolver;

    public function __construct()
    {
        $this->tableMapResolver = new StatementTableMapResolver();
    }

    /**
     * @return list<ResolvedSqlParameter>
     */
    public function resolve(SqlStatement $statement, DatabaseSchema $schema): array
    {
        $tableMap = $this->tableMapResolver->resolve($statement, $schema);
        $resolvedByName = [];

        foreach ($this->extractColumnComparisons($statement->sql) as $comparison) {
            $resolvedColumn = $tableMap->resolveColumn($comparison['qualifier'], $comparison['column']);

            $resolvedByName[$comparison['param']] = new ResolvedSqlParameter(
                name: $comparison['param'],
                propertyName: $this->snakeToCamel($comparison['param']),
                sqlType: $resolvedColumn->column->sqlType,
                phpType: $resolvedColumn->column->phpType,
                nullable: $resolvedColumn->column->nullable,
            );
        }

        foreach ($this->extractInsertValueMappings($statement->sql) as $mapping) {
            $resolvedColumn = $tableMap->resolveColumn($mapping['qualifier'], $mapping['column']);

            $resolvedByName[$mapping['param']] ??= new ResolvedSqlParameter(
                name: $mapping['param'],
                propertyName: $this->snakeToCamel($mapping['param']),
                sqlType: $resolvedColumn->column->sqlType,
                phpType: $resolvedColumn->column->phpType,
                nullable: $resolvedColumn->column->nullable,
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

    /**
     * @return list<array{qualifier: string|null, column: string, param: string}>
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
            $column = $match['left_column'] ?? $match['right_column'] ?? null;
            $param = $match['right_param'] ?? $match['left_param'] ?? null;
            $qualifier = null;

            if (array_key_exists('left_table', $match)) {
                $qualifier = strtolower($match['left_table']);
            } elseif (array_key_exists('right_table', $match)) {
                $qualifier = strtolower($match['right_table']);
            }

            if ($column === null || $param === null) {
                continue;
            }

            $comparisons[] = [
                'qualifier' => $qualifier,
                'column' => $column,
                'param' => $param,
            ];
        }

        return $comparisons;
    }

    /**
     * @return list<array{qualifier: string|null, column: string, param: string}>
     */
    private function extractInsertValueMappings(string $sql): array
    {
        if (!preg_match(
            '/\bINSERT\s+INTO\s+(?<table>[a-zA-Z_][a-zA-Z0-9_]*)\s*\((?<columns>.*?)\)\s*VALUES\s*\((?<values>.*?)\)/is',
            $sql,
            $matches,
        )) {
            return [];
        }

        $columnsExpression = $matches['columns'];
        $valuesExpression = $matches['values'];

        $columns = preg_split('/\s*,\s*/', trim($columnsExpression));
        $values = preg_split('/\s*,\s*/', trim($valuesExpression));

        if (!is_array($columns) || !is_array($values) || count($columns) !== count($values)) {
            throw new \RuntimeException('Unable to parse INSERT column/value mappings');
        }

        $mappings = [];

        foreach ($columns as $index => $columnExpression) {
            $column = trim($columnExpression);
            $value = trim($values[$index]);

            if (!preg_match('/^(?<column>[a-zA-Z_][a-zA-Z0-9_]*)$/', $column, $columnMatches)) {
                throw new \RuntimeException("Unsupported INSERT column expression '{$column}'");
            }

            if (!preg_match('/^:(?<param>[a-zA-Z_][a-zA-Z0-9_]*)$/', $value, $valueMatches)) {
                continue;
            }

            $mappings[] = [
                'qualifier' => strtolower($matches['table']),
                'column' => $columnMatches['column'],
                'param' => $valueMatches['param'],
            ];
        }

        return $mappings;
    }

    private function snakeToCamel(string $value): string
    {
        return lcfirst(str_replace('_', '', ucwords($value, '_')));
    }
}

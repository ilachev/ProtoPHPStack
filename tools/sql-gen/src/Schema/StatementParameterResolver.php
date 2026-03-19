<?php

declare(strict_types=1);

namespace SqlGen\Schema;

use SqlGen\Ast\InsertQuery;
use SqlGen\Ast\SelectColumnReference;
use SqlGen\Ast\SelectComparison;
use SqlGen\Ast\SelectPlaceholder;
use SqlGen\Ast\SelectQuery;
use SqlGen\Model\DatabaseSchema;
use SqlGen\Model\ResolvedSqlParameter;
use SqlGen\Model\SqlStatement;
use SqlGen\Parser\PhplrtSqlParser;

final class StatementParameterResolver
{
    private StatementTableMapResolver $tableMapResolver;
    private PhplrtSqlParser $sqlParser;

    public function __construct()
    {
        $this->tableMapResolver = new StatementTableMapResolver();
        $this->sqlParser = new PhplrtSqlParser();
    }

    /**
     * @return list<ResolvedSqlParameter>
     */
    public function resolve(SqlStatement $statement, DatabaseSchema $schema): array
    {
        $tableMap = $this->tableMapResolver->resolve($statement, $schema);
        $resolvedByName = [];

        foreach ($this->extractParameterMappings($statement) as $mapping) {
            $resolvedColumn = $tableMap->resolveColumn($mapping['qualifier'], $mapping['column']);

            $resolvedByName[$mapping['param']] = new ResolvedSqlParameter(
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
    private function extractParameterMappings(SqlStatement $statement): array
    {
        $query = $this->sqlParser->parse($statement->sql);

        if ($query instanceof SelectQuery) {
            $comparisons = [];

            foreach ($query->joins as $join) {
                $comparison = $this->comparisonToParameterMapping($join->condition);
                if ($comparison !== null) {
                    $comparisons[] = $comparison;
                }
            }

            foreach ($query->where as $comparisonNode) {
                $comparison = $this->comparisonToParameterMapping($comparisonNode);
                if ($comparison !== null) {
                    $comparisons[] = $comparison;
                }
            }

            return $comparisons;
        }

        if ($query instanceof InsertQuery) {
            $mappings = [];

            foreach ($query->values as $valueMapping) {
                $mappings[] = [
                    'qualifier' => strtolower($query->table),
                    'column' => $valueMapping->column,
                    'param' => $valueMapping->placeholder->name,
                ];
            }

            return $mappings;
        }

        $comparisons = [];

        foreach ($query->where as $comparisonNode) {
            $comparison = $this->comparisonToParameterMapping($comparisonNode, strtolower($query->table));
            if ($comparison !== null) {
                $comparisons[] = $comparison;
            }
        }

        return $comparisons;
    }

    /**
     * @return array{qualifier: string|null, column: string, param: string}|null
     */
    private function comparisonToParameterMapping(SelectComparison $comparison, ?string $defaultQualifier = null): ?array
    {
        if ($comparison->left instanceof SelectColumnReference && $comparison->right instanceof SelectPlaceholder) {
            return [
                'qualifier' => $comparison->left->table !== null ? strtolower($comparison->left->table) : $defaultQualifier,
                'column' => $comparison->left->column,
                'param' => $comparison->right->name,
            ];
        }

        if ($comparison->left instanceof SelectPlaceholder && $comparison->right instanceof SelectColumnReference) {
            return [
                'qualifier' => $comparison->right->table !== null ? strtolower($comparison->right->table) : $defaultQualifier,
                'column' => $comparison->right->column,
                'param' => $comparison->left->name,
            ];
        }

        return null;
    }

    private function snakeToCamel(string $value): string
    {
        return lcfirst(str_replace('_', '', ucwords($value, '_')));
    }
}

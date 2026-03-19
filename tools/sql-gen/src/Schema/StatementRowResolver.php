<?php

declare(strict_types=1);

namespace SqlGen\Schema;

use SqlGen\Ast\SelectProjectionColumn;
use SqlGen\Ast\SelectProjectionWildcard;
use SqlGen\Model\DatabaseSchema;
use SqlGen\Model\RowField;
use SqlGen\Model\SqlResultKind;
use SqlGen\Model\SqlStatement;
use SqlGen\Parser\PhplrtSelectParser;

final class StatementRowResolver
{
    private StatementTableMapResolver $tableMapResolver;
    private PhplrtSelectParser $selectParser;

    public function __construct()
    {
        $this->tableMapResolver = new StatementTableMapResolver();
        $this->selectParser = new PhplrtSelectParser();
    }

    /**
     * @return list<RowField>
     */
    public function resolve(SqlStatement $statement, DatabaseSchema $schema): array
    {
        if ($statement->resultKind === SqlResultKind::Exec) {
            return [];
        }

        $tableMap = $this->tableMapResolver->resolve($statement, $schema);
        $columns = $this->resolveSelectedColumns($statement, $tableMap);

        $fields = [];
        $seenResultColumns = [];

        foreach ($columns as $column) {
            if (isset($seenResultColumns[$column['result']])) {
                throw new \RuntimeException(
                    "Duplicate result column {$column['result']} in query {$statement->name}",
                );
            }

            $seenResultColumns[$column['result']] = true;
            $resolvedColumn = $tableMap->resolveColumn($column['qualifier'], $column['source']);

            $fields[] = new RowField(
                sourceColumnName: $column['source'],
                resultColumnName: $column['result'],
                propertyName: $this->snakeToCamel($column['result']),
                phpType: $resolvedColumn->column->phpType,
                nullable: $resolvedColumn->column->nullable,
            );
        }

        return $fields;
    }

    /**
     * @return list<array{qualifier: string|null, source: string, result: string}>
     */
    private function resolveSelectedColumns(SqlStatement $statement, StatementTableMap $tableMap): array
    {
        if (preg_match('/\bSELECT\s+(?<columns>.*?)\s+FROM\b/is', $statement->sql, $matches)) {
            try {
                return $this->resolveSelectColumnsViaAst($statement, $tableMap);
            } catch (\Throwable) {
                return $this->resolveSelectColumnsViaRegex($statement, $tableMap);
            }
        }

        if (preg_match('/\bRETURNING\s+(?<columns>.*?)(?:;|$)/is', $statement->sql, $matches)) {
            $columnsExpression = trim($matches['columns']);
        } else {
            throw new \RuntimeException("Unable to resolve row columns for query {$statement->name}");
        }

        if ($columnsExpression === '*') {
            return $this->expandWildcardColumns($tableMap, null);
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

            if (preg_match('/^(?<table>[a-zA-Z_][a-zA-Z0-9_]*)\.\*$/i', $column, $wildcard)) {
                array_push($columns, ...$this->expandWildcardColumns($tableMap, strtolower($wildcard['table'])));
                continue;
            }

            if (preg_match('/^(?:(?<table>[a-zA-Z_][a-zA-Z0-9_]*)\.)?(?<column>[a-zA-Z_][a-zA-Z0-9_]*)(?:\s+AS\s+(?<alias>[a-zA-Z_][a-zA-Z0-9_]*))?$/i', $column, $named)) {
                $qualifier = $named['table'] !== '' ? strtolower($named['table']) : null;
                $sourceColumn = $named['column'];
                $alias = $named['alias'] ?? null;
                $resultColumn = is_string($alias) ? $alias : $sourceColumn;

                $columns[] = [
                    'qualifier' => $qualifier,
                    'source' => $sourceColumn,
                    'result' => $resultColumn,
                ];
                continue;
            }

            throw new \RuntimeException("Unsupported selected column expression '{$column}' in query {$statement->name}");
        }

        return $columns;
    }

    /**
     * @return list<array{qualifier: string|null, source: string, result: string}>
     */
    private function resolveSelectColumnsViaAst(SqlStatement $statement, StatementTableMap $tableMap): array
    {
        $select = $this->selectParser->parse($statement->sql);
        $columns = [];

        foreach ($select->projections as $projection) {
            if ($projection instanceof SelectProjectionWildcard) {
                array_push($columns, ...$this->expandWildcardColumns($tableMap, $projection->table));
                continue;
            }

            if ($projection instanceof SelectProjectionColumn) {
                $columns[] = [
                    'qualifier' => $projection->reference->table !== null ? strtolower($projection->reference->table) : null,
                    'source' => $projection->reference->column,
                    'result' => $projection->alias !== null ? $projection->alias->value : $projection->reference->column,
                ];
                continue;
            }

            throw new \RuntimeException("Unsupported SELECT projection type in query {$statement->name}");
        }

        return $columns;
    }

    /**
     * @return list<array{qualifier: string|null, source: string, result: string}>
     */
    private function resolveSelectColumnsViaRegex(SqlStatement $statement, StatementTableMap $tableMap): array
    {
        if (!preg_match('/\bSELECT\s+(?<columns>.*?)\s+FROM\b/is', $statement->sql, $matches)) {
            throw new \RuntimeException("Unable to resolve row columns for query {$statement->name}");
        }

        $columnsExpression = trim($matches['columns']);
        if ($columnsExpression === '*') {
            return $this->expandWildcardColumns($tableMap, null);
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

            if (preg_match('/^(?<table>[a-zA-Z_][a-zA-Z0-9_]*)\.\*$/i', $column, $wildcard)) {
                array_push($columns, ...$this->expandWildcardColumns($tableMap, strtolower($wildcard['table'])));
                continue;
            }

            if (preg_match('/^(?:(?<table>[a-zA-Z_][a-zA-Z0-9_]*)\.)?(?<column>[a-zA-Z_][a-zA-Z0-9_]*)(?:\s+AS\s+(?<alias>[a-zA-Z_][a-zA-Z0-9_]*))?$/i', $column, $named)) {
                $qualifier = $named['table'] !== '' ? strtolower($named['table']) : null;
                $sourceColumn = $named['column'];
                $alias = $named['alias'] ?? null;

                $columns[] = [
                    'qualifier' => $qualifier,
                    'source' => $sourceColumn,
                    'result' => is_string($alias) ? $alias : $sourceColumn,
                ];
                continue;
            }

            throw new \RuntimeException("Unsupported selected column expression '{$column}' in query {$statement->name}");
        }

        return $columns;
    }

    /**
     * @return list<array{qualifier: string|null, source: string, result: string}>
     */
    private function expandWildcardColumns(StatementTableMap $tableMap, ?string $qualifier): array
    {
        $columns = [];

        foreach ($tableMap->expandWildcard($qualifier) as $column) {
            $columns[] = [
                'qualifier' => $qualifier,
                'source' => $column->name,
                'result' => $column->name,
            ];
        }

        return $columns;
    }

    private function snakeToCamel(string $value): string
    {
        return lcfirst(str_replace('_', '', ucwords($value, '_')));
    }
}

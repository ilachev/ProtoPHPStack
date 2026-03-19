<?php

declare(strict_types=1);

namespace SqlGen\Schema;

use SqlGen\Ast\InsertQuery;
use SqlGen\Ast\SelectProjection;
use SqlGen\Ast\SelectProjectionColumn;
use SqlGen\Ast\SelectProjectionWildcard;
use SqlGen\Ast\SelectQuery;
use SqlGen\Model\DatabaseSchema;
use SqlGen\Model\RowField;
use SqlGen\Model\SqlResultKind;
use SqlGen\Model\SqlStatement;
use SqlGen\Parser\PhplrtSqlParser;

final class StatementRowResolver
{
    private StatementTableMapResolver $tableMapResolver;
    private PhplrtSqlParser $sqlParser;

    public function __construct()
    {
        $this->tableMapResolver = new StatementTableMapResolver();
        $this->sqlParser = new PhplrtSqlParser();
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
        $query = $this->sqlParser->parse($statement->sql);

        if ($query instanceof SelectQuery) {
            return $this->resolveProjections($query->projections, $statement->name, $tableMap);
        }

        if (!$query instanceof InsertQuery) {
            throw new \RuntimeException("SQL statement {$statement->name} does not expose row projections.");
        }

        return $this->resolveProjections($query->returning, $statement->name, $tableMap);
    }

    /**
     * @param list<SelectProjection> $projections
     * @return list<array{qualifier: string|null, source: string, result: string}>
     */
    private function resolveProjections(array $projections, string $queryName, StatementTableMap $tableMap): array
    {
        $columns = [];

        foreach ($projections as $projection) {
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

            throw new \RuntimeException("Unsupported projection type in query {$queryName}");
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

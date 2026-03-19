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
use SqlGen\Parser\SqlQueryParser;

final class StatementRowResolver
{
    private StatementTableMapResolver $tableMapResolver;
    private SqlQueryParser $sqlParser;

    public function __construct(
        ?StatementTableMapResolver $tableMapResolver = null,
        ?SqlQueryParser $sqlParser = null,
    ) {
        $this->tableMapResolver = $tableMapResolver ?? new StatementTableMapResolver();
        $this->sqlParser = $sqlParser ?? new PhplrtSqlParser();
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
            if (isset($seenResultColumns[$column->resultColumn])) {
                throw new \RuntimeException(
                    "Duplicate result column {$column->resultColumn} in query {$statement->name}",
                );
            }

            $seenResultColumns[$column->resultColumn] = true;
            $resolvedColumn = $tableMap->resolveColumn($column->qualifier, $column->sourceColumn);

            $fields[] = new RowField(
                sourceColumnName: $column->sourceColumn,
                resultColumnName: $column->resultColumn,
                propertyName: $this->snakeToCamel($column->resultColumn),
                phpType: $resolvedColumn->column->phpType,
                nullable: $resolvedColumn->column->nullable,
            );
        }

        return $fields;
    }

    /**
     * @return list<ResolvedProjectionColumn>
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
     * @return list<ResolvedProjectionColumn>
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
                $columns[] = new ResolvedProjectionColumn(
                    qualifier: $projection->reference->table !== null ? strtolower($projection->reference->table) : null,
                    sourceColumn: $projection->reference->column,
                    resultColumn: $projection->alias !== null ? $projection->alias->value : $projection->reference->column,
                );
                continue;
            }

            throw new \RuntimeException("Unsupported projection type in query {$queryName}");
        }

        return $columns;
    }

    /**
     * @return list<ResolvedProjectionColumn>
     */
    private function expandWildcardColumns(StatementTableMap $tableMap, ?string $qualifier): array
    {
        $columns = [];

        foreach ($tableMap->expandWildcard($qualifier) as $column) {
            $columns[] = new ResolvedProjectionColumn(
                qualifier: $qualifier,
                sourceColumn: $column->name,
                resultColumn: $column->name,
            );
        }

        return $columns;
    }

    private function snakeToCamel(string $value): string
    {
        return lcfirst(str_replace('_', '', ucwords($value, '_')));
    }
}

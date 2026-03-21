<?php

declare(strict_types=1);

namespace SqlGen\Schema;

use SqlGen\Ast\DeleteQuery;
use SqlGen\Ast\InsertQuery;
use SqlGen\Ast\SelectQuery;
use SqlGen\Model\DatabaseSchema;
use SqlGen\Model\SqlStatement;
use SqlGen\Parser\PhplrtSqlParser;
use SqlGen\Parser\SqlQueryParser;

final class StatementTableMapResolver
{
    private SqlQueryParser $sqlParser;

    public function __construct(
        ?SqlQueryParser $sqlParser = null,
    ) {
        $this->sqlParser = $sqlParser ?? new PhplrtSqlParser();
    }

    public function resolve(SqlStatement $statement, DatabaseSchema $schema): StatementTableMap
    {
        $visibleTables = [];
        $query = $this->sqlParser->parse($statement->sql);
        $primaryTable = null;

        if ($query instanceof SelectQuery) {
            $primaryTable = $this->resolveVisibleTable($query->from->table, $query->from->alias, $schema, $statement->name);
            $visibleTables[] = $primaryTable;

            foreach ($query->joins as $join) {
                $visibleTables[] = $this->resolveVisibleTable(
                    $join->table->table,
                    $join->table->alias,
                    $schema,
                    $statement->name,
                );
            }
        }

        if ($query instanceof InsertQuery) {
            $primaryTable = $this->resolveVisibleTable($query->table, null, $schema, $statement->name);
            $visibleTables[] = $primaryTable;
        }

        if ($query instanceof DeleteQuery) {
            $primaryTable = $this->resolveVisibleTable($query->table, null, $schema, $statement->name);
            $visibleTables[] = $primaryTable;
        }

        if (!$primaryTable instanceof StatementVisibleTable) {
            throw new \RuntimeException("Unable to resolve table name for query {$statement->name}");
        }

        return new StatementTableMap(
            visibleTables: $visibleTables,
            primaryTable: $primaryTable,
            queryName: $statement->name,
        );
    }

    private function resolveVisibleTable(
        string $tableName,
        ?string $alias,
        DatabaseSchema $schema,
        string $queryName,
    ): StatementVisibleTable {
        $normalizedTableName = strtolower($tableName);
        $table = $schema->getTable($normalizedTableName);
        if ($table === null) {
            throw new \RuntimeException("Table {$normalizedTableName} was not found in schema for query {$queryName}");
        }

        return new StatementVisibleTable($table, $alias);
    }
}

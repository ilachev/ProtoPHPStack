<?php

declare(strict_types=1);

namespace SqlGen\Schema;

use SqlGen\Ast\DeleteQuery;
use SqlGen\Ast\InsertQuery;
use SqlGen\Ast\SelectQuery;
use SqlGen\Model\DatabaseSchema;
use SqlGen\Model\SchemaTable;
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
        $references = [];
        $tables = [];
        $query = $this->sqlParser->parse($statement->sql);
        $primaryTable = null;

        if ($query instanceof SelectQuery) {
            $primaryTable = $this->resolveSchemaTable($query->from->table, $schema, $statement->name);
            $references[$primaryTable->name] = $primaryTable;
            $tables[] = $primaryTable;

            if ($query->from->alias !== null) {
                $references[strtolower($query->from->alias)] = $primaryTable;
            }

            foreach ($query->joins as $join) {
                $table = $this->resolveSchemaTable($join->table->table, $schema, $statement->name);
                $references[$table->name] = $table;

                if ($join->table->alias !== null) {
                    $references[strtolower($join->table->alias)] = $table;
                }

                if (!$this->containsTable($tables, $table)) {
                    $tables[] = $table;
                }
            }
        }

        if ($query instanceof InsertQuery) {
            $primaryTable = $this->resolveSchemaTable($query->table, $schema, $statement->name);
            $references[$primaryTable->name] = $primaryTable;
            $tables[] = $primaryTable;
        }

        if ($query instanceof DeleteQuery) {
            $primaryTable = $this->resolveSchemaTable($query->table, $schema, $statement->name);
            $references[$primaryTable->name] = $primaryTable;
            $tables[] = $primaryTable;
        }

        if (!$primaryTable instanceof SchemaTable) {
            throw new \RuntimeException("Unable to resolve table name for query {$statement->name}");
        }

        return new StatementTableMap(
            references: $references,
            tables: $tables,
            primaryTable: $primaryTable,
            queryName: $statement->name,
        );
    }

    private function resolveSchemaTable(string $tableName, DatabaseSchema $schema, string $queryName): SchemaTable
    {
        $normalizedTableName = strtolower($tableName);
        $table = $schema->getTable($normalizedTableName);
        if ($table === null) {
            throw new \RuntimeException("Table {$normalizedTableName} was not found in schema for query {$queryName}");
        }

        return $table;
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

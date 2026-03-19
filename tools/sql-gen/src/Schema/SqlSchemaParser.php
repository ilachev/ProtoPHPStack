<?php

declare(strict_types=1);

namespace SqlGen\Schema;

use SqlGen\Model\DatabaseSchema;
use SqlGen\Model\SchemaColumn;
use SqlGen\Model\SchemaTable;

final class SqlSchemaParser
{
    public function parseFile(string $path): DatabaseSchema
    {
        $contents = file_get_contents($path);
        if (!\is_string($contents)) {
            throw new \RuntimeException("Unable to read schema file: {$path}");
        }

        $tables = [];
        $tableDefinitions = (new SchemaSqlParser())->parse($contents);

        foreach ($tableDefinitions as $tableDefinition) {
            $tables[$tableDefinition->name] = $this->buildTable($tableDefinition);
        }

        return new DatabaseSchema($tables);
    }

    private function buildTable(SchemaTableDefinition $definition): SchemaTable
    {
        $columns = [];

        foreach ($definition->columns as $columnDefinition) {
            $columns[$columnDefinition->name] = new SchemaColumn(
                name: $columnDefinition->name,
                sqlType: $columnDefinition->sqlType,
                phpType: $this->mapPhpType($columnDefinition->sqlType),
                nullable: $columnDefinition->nullable,
            );
        }

        return new SchemaTable($definition->name, $columns);
    }

    private function mapPhpType(string $sqlType): string
    {
        return match ($sqlType) {
            'TEXT', 'JSONB' => 'string',
            'INTEGER', 'BIGINT', 'BIGSERIAL', 'SERIAL' => 'int',
            'REAL', 'DOUBLE', 'NUMERIC', 'DECIMAL' => 'float',
            'BOOLEAN', 'BOOL' => 'bool',
            default => throw new \RuntimeException("Unsupported SQL type for PHP mapping: {$sqlType}"),
        };
    }
}

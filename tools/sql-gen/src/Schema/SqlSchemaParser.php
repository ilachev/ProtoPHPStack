<?php

declare(strict_types=1);

namespace SqlGen\Schema;

use SqlGen\Model\DatabaseSchema;
use SqlGen\Model\SchemaColumn;
use SqlGen\Model\SchemaTable;

final class SqlSchemaParser
{
    private const CREATE_TABLE_PATTERN = '/CREATE\s+TABLE\s+(?<name>[a-zA-Z_][a-zA-Z0-9_]*)\s*\((?<body>.*?)\);/is';

    public function parseFile(string $path): DatabaseSchema
    {
        $contents = file_get_contents($path);
        if (!\is_string($contents)) {
            throw new \RuntimeException("Unable to read schema file: {$path}");
        }

        preg_match_all(self::CREATE_TABLE_PATTERN, $contents, $matches, PREG_SET_ORDER);

        $tables = [];

        foreach ($matches as $match) {
            $tableName = $match['name'];
            $body = $match['body'];

            $tables[$tableName] = $this->parseTable($tableName, $body);
        }

        return new DatabaseSchema($tables);
    }

    private function parseTable(string $tableName, string $body): SchemaTable
    {
        $columns = [];
        $lines = preg_split('/,\s*\n/', trim($body));

        if (!\is_array($lines)) {
            throw new \RuntimeException("Unable to parse table body for {$tableName}");
        }

        foreach ($lines as $line) {
            $definition = trim($line);
            $definition = rtrim($definition, ',');

            if ($definition === '' || $this->isTableConstraint($definition)) {
                continue;
            }

            if (!preg_match('/^(?<name>[a-zA-Z_][a-zA-Z0-9_]*)\s+(?<type>[a-zA-Z0-9_]+)/', $definition, $matches)) {
                throw new \RuntimeException("Unsupported column definition in {$tableName}: {$definition}");
            }

            $columnName = $matches['name'];
            $sqlType = strtoupper($matches['type']);
            $nullable = !str_contains(strtoupper($definition), 'NOT NULL')
                && !str_contains(strtoupper($definition), 'PRIMARY KEY');

            $columns[$columnName] = new SchemaColumn(
                name: $columnName,
                sqlType: $sqlType,
                phpType: $this->mapPhpType($sqlType),
                nullable: $nullable,
            );
        }

        return new SchemaTable($tableName, $columns);
    }

    private function isTableConstraint(string $definition): bool
    {
        $upper = strtoupper($definition);

        return str_starts_with($upper, 'PRIMARY KEY')
            || str_starts_with($upper, 'UNIQUE')
            || str_starts_with($upper, 'CONSTRAINT')
            || str_starts_with($upper, 'FOREIGN KEY');
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

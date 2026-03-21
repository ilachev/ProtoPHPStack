<?php

declare(strict_types=1);

namespace SqlGen\Schema;

use SqlGen\Model\DatabaseSchema;
use SqlGen\Model\SchemaColumn;
use SqlGen\Model\SchemaForeignKeyConstraint;
use SqlGen\Model\SchemaTable;
use SqlGen\Model\SchemaTableReference;
use SqlGen\Model\SchemaUniqueConstraint;

final class SqlSchemaParser implements DatabaseSchemaParser
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
        $primaryKeyColumns = $this->normalizePrimaryKeyColumns($definition);
        $uniqueConstraints = $this->normalizeUniqueConstraints($definition);
        $foreignKeys = $this->normalizeForeignKeys($definition);
        $columns = [];

        foreach ($definition->columns as $columnDefinition) {
            $reference = $columnDefinition->reference;
            if ($reference === null) {
                $reference = $this->resolveSingleColumnReference($columnDefinition->name, $foreignKeys);
            }

            $columns[$columnDefinition->name] = new SchemaColumn(
                name: $columnDefinition->name,
                sqlType: $columnDefinition->sqlType,
                phpType: $this->mapPhpType($columnDefinition->sqlType),
                nullable: $columnDefinition->nullable,
                primaryKey: in_array($columnDefinition->name, $primaryKeyColumns, true) || $columnDefinition->primaryKey,
                unique: $columnDefinition->unique || $this->isSingleColumnUnique($columnDefinition->name, $uniqueConstraints),
                reference: $reference !== null
                    ? new SchemaTableReference($reference->table, $reference->column)
                    : null,
            );
        }

        return new SchemaTable(
            name: $definition->name,
            columns: $columns,
            primaryKeyColumns: $primaryKeyColumns,
            uniqueConstraints: array_map(
                static fn(SchemaUniqueConstraintDefinition $constraint): SchemaUniqueConstraint => new SchemaUniqueConstraint($constraint->columns),
                $uniqueConstraints,
            ),
            foreignKeys: array_map(
                static fn(SchemaForeignKeyConstraintDefinition $constraint): SchemaForeignKeyConstraint => new SchemaForeignKeyConstraint(
                    columns: $constraint->columns,
                    referencedTable: $constraint->referencedTable,
                    referencedColumns: $constraint->referencedColumns,
                ),
                $foreignKeys,
            ),
        );
    }

    /**
     * @return list<string>
     */
    private function normalizePrimaryKeyColumns(SchemaTableDefinition $definition): array
    {
        $columns = $definition->primaryKeyColumns;

        foreach ($definition->columns as $columnDefinition) {
            if ($columnDefinition->primaryKey) {
                $columns[] = $columnDefinition->name;
            }
        }

        return array_values(array_unique($columns));
    }

    /**
     * @return list<SchemaUniqueConstraintDefinition>
     */
    private function normalizeUniqueConstraints(SchemaTableDefinition $definition): array
    {
        $constraints = $definition->uniqueConstraints;

        foreach ($definition->columns as $columnDefinition) {
            if ($columnDefinition->unique) {
                $constraints[] = new SchemaUniqueConstraintDefinition([$columnDefinition->name]);
            }
        }

        return $this->deduplicateUniqueConstraints($constraints);
    }

    /**
     * @return list<SchemaForeignKeyConstraintDefinition>
     */
    private function normalizeForeignKeys(SchemaTableDefinition $definition): array
    {
        $foreignKeys = $definition->foreignKeys;

        foreach ($definition->columns as $columnDefinition) {
            if ($columnDefinition->reference === null) {
                continue;
            }

            $foreignKeys[] = new SchemaForeignKeyConstraintDefinition(
                columns: [$columnDefinition->name],
                referencedTable: $columnDefinition->reference->table,
                referencedColumns: [$columnDefinition->reference->column],
            );
        }

        return $this->deduplicateForeignKeys($foreignKeys);
    }

    /**
     * @param list<SchemaUniqueConstraintDefinition> $constraints
     * @return list<SchemaUniqueConstraintDefinition>
     */
    private function deduplicateUniqueConstraints(array $constraints): array
    {
        $uniqueByKey = [];

        foreach ($constraints as $constraint) {
            $key = implode('|', $constraint->columns);
            $uniqueByKey[$key] = $constraint;
        }

        return array_values($uniqueByKey);
    }

    /**
     * @param list<SchemaForeignKeyConstraintDefinition> $foreignKeys
     * @return list<SchemaForeignKeyConstraintDefinition>
     */
    private function deduplicateForeignKeys(array $foreignKeys): array
    {
        $foreignKeysByKey = [];

        foreach ($foreignKeys as $foreignKey) {
            $key = implode('|', $foreignKey->columns)
                . '=>'
                . $foreignKey->referencedTable
                . '(' . implode('|', $foreignKey->referencedColumns) . ')';
            $foreignKeysByKey[$key] = $foreignKey;
        }

        return array_values($foreignKeysByKey);
    }

    /**
     * @param list<SchemaUniqueConstraintDefinition> $constraints
     */
    private function isSingleColumnUnique(string $columnName, array $constraints): bool
    {
        foreach ($constraints as $constraint) {
            if (count($constraint->columns) === 1 && $constraint->columns[0] === $columnName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<SchemaForeignKeyConstraintDefinition> $foreignKeys
     */
    private function resolveSingleColumnReference(string $columnName, array $foreignKeys): ?SchemaTableReferenceDefinition
    {
        foreach ($foreignKeys as $foreignKey) {
            if (count($foreignKey->columns) !== 1 || count($foreignKey->referencedColumns) !== 1) {
                continue;
            }

            if ($foreignKey->columns[0] !== $columnName) {
                continue;
            }

            return new SchemaTableReferenceDefinition(
                table: $foreignKey->referencedTable,
                column: $foreignKey->referencedColumns[0],
            );
        }

        return null;
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

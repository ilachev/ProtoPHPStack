<?php

declare(strict_types=1);

namespace SqlGen\Schema;

use SqlGen\Model\DatabaseSchema;
use SqlGen\Model\SchemaForeignKeyConstraint;
use SqlGen\Model\SchemaTable;

final class SchemaIntegrityValidator
{
    public function validate(DatabaseSchema $schema): void
    {
        foreach ($schema->tables as $table) {
            $this->validatePrimaryKeyColumns($table);
            $this->validateUniqueConstraints($table);
            $this->validateForeignKeys($table, $schema);
        }
    }

    private function validatePrimaryKeyColumns(SchemaTable $table): void
    {
        foreach ($table->primaryKeyColumns as $columnName) {
            if ($table->getColumn($columnName) !== null) {
                continue;
            }

            throw new \RuntimeException(
                "PRIMARY KEY column {$columnName} was not found in schema table {$table->name}",
            );
        }
    }

    private function validateUniqueConstraints(SchemaTable $table): void
    {
        foreach ($table->uniqueConstraints as $constraint) {
            $this->validateConstraintColumns($table, $constraint->columns, 'UNIQUE');
        }
    }

    private function validateForeignKeys(SchemaTable $table, DatabaseSchema $schema): void
    {
        foreach ($table->foreignKeys as $foreignKey) {
            $this->validateForeignKeyLocalColumns($table, $foreignKey);
            $this->validateForeignKeyTarget($table, $foreignKey, $schema);
        }
    }

    /**
     * @param list<string> $columns
     */
    private function validateConstraintColumns(SchemaTable $table, array $columns, string $constraintName): void
    {
        foreach ($columns as $columnName) {
            if ($table->getColumn($columnName) !== null) {
                continue;
            }

            throw new \RuntimeException(
                "{$constraintName} column {$columnName} was not found in schema table {$table->name}",
            );
        }
    }

    private function validateForeignKeyLocalColumns(SchemaTable $table, SchemaForeignKeyConstraint $foreignKey): void
    {
        $this->validateConstraintColumns($table, $foreignKey->columns, 'FOREIGN KEY');
    }

    private function validateForeignKeyTarget(
        SchemaTable $table,
        SchemaForeignKeyConstraint $foreignKey,
        DatabaseSchema $schema,
    ): void {
        if (count($foreignKey->columns) !== count($foreignKey->referencedColumns)) {
            throw new \RuntimeException(
                "FOREIGN KEY on table {$table->name} must reference the same number of columns as its local columns",
            );
        }

        $referencedTable = $schema->getTable(strtolower($foreignKey->referencedTable));
        if ($referencedTable === null) {
            throw new \RuntimeException(
                "FOREIGN KEY on table {$table->name} references unknown table {$foreignKey->referencedTable}",
            );
        }

        foreach ($foreignKey->referencedColumns as $columnName) {
            if ($referencedTable->getColumn($columnName) !== null) {
                continue;
            }

            throw new \RuntimeException(
                "FOREIGN KEY on table {$table->name} references unknown column {$columnName} on table {$referencedTable->name}",
            );
        }
    }
}

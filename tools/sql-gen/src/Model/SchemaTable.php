<?php

declare(strict_types=1);

namespace SqlGen\Model;

final readonly class SchemaTable
{
    /**
     * @param array<string, SchemaColumn> $columns
     * @param list<string> $primaryKeyColumns
     * @param list<SchemaUniqueConstraint> $uniqueConstraints
     * @param list<SchemaForeignKeyConstraint> $foreignKeys
     */
    public function __construct(
        public string $name,
        public array $columns,
        public array $primaryKeyColumns = [],
        public array $uniqueConstraints = [],
        public array $foreignKeys = [],
    ) {}

    public function getColumn(string $name): ?SchemaColumn
    {
        return $this->columns[$name] ?? null;
    }

    /**
     * @param list<string> $columns
     */
    public function supportsConflictTarget(array $columns): bool
    {
        return $this->matchesColumnSet($this->primaryKeyColumns, $columns)
            || $this->hasMatchingUniqueConstraint($columns);
    }

    /**
     * @param list<string> $columns
     */
    private function hasMatchingUniqueConstraint(array $columns): bool
    {
        foreach ($this->uniqueConstraints as $constraint) {
            if ($this->matchesColumnSet($constraint->columns, $columns)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     */
    private function matchesColumnSet(array $left, array $right): bool
    {
        if (count($left) !== count($right)) {
            return false;
        }

        $normalizedLeft = array_map(static fn(string $column): string => strtolower($column), $left);
        $normalizedRight = array_map(static fn(string $column): string => strtolower($column), $right);

        sort($normalizedLeft);
        sort($normalizedRight);

        return $normalizedLeft === $normalizedRight;
    }
}

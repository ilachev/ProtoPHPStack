<?php

declare(strict_types=1);

namespace SqlGen\Ast;

final readonly class InsertConflictClause
{
    /**
     * @param list<string> $targetColumns
     * @param list<InsertConflictAssignment> $assignments
     */
    public function __construct(
        public array $targetColumns,
        public array $assignments,
    ) {}
}

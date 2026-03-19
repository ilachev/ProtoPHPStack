<?php

declare(strict_types=1);

namespace SqlGen\Ast;

final readonly class InsertConflictAssignment
{
    public function __construct(
        public string $column,
        public string $excludedColumn,
    ) {}
}

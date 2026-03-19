<?php

declare(strict_types=1);

namespace SqlGen\Ast;

final readonly class InsertQuery implements SqlQuery
{
    /**
     * @param list<InsertValueMapping> $values
     * @param list<SelectProjection> $returning
     */
    public function __construct(
        public string $table,
        public array $values,
        public ?InsertConflictClause $conflict,
        public array $returning,
    ) {}
}

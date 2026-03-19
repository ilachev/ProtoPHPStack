<?php

declare(strict_types=1);

namespace SqlGen\Ast;

final readonly class DeleteQuery implements SqlQuery
{
    /**
     * @param list<SelectComparison> $where
     * @param list<string> $whereOperators
     */
    public function __construct(
        public string $table,
        public array $where,
        public array $whereOperators,
    ) {}
}

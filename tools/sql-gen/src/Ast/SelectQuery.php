<?php

declare(strict_types=1);

namespace SqlGen\Ast;

final readonly class SelectQuery
{
    /**
     * @param list<SelectProjection> $projections
     * @param list<SelectJoin> $joins
     * @param list<SelectComparison> $where
     * @param list<string> $whereOperators
     * @param list<SelectOrderByItem> $orderBy
     */
    public function __construct(
        public array $projections,
        public SelectTableReference $from,
        public array $joins,
        public array $where,
        public array $whereOperators,
        public array $orderBy,
    ) {}
}

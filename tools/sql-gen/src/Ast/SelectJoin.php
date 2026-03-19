<?php

declare(strict_types=1);

namespace SqlGen\Ast;

final readonly class SelectJoin
{
    public function __construct(
        public ?string $type,
        public SelectTableReference $table,
        public SelectComparison $condition,
    ) {}
}

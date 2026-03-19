<?php

declare(strict_types=1);

namespace SqlGen\Ast;

final readonly class SelectComparison
{
    public function __construct(
        public SelectOperand $left,
        public string $operator,
        public SelectOperand $right,
    ) {}
}

<?php

declare(strict_types=1);

namespace SqlGen\Ast;

final readonly class SelectCaseWhen
{
    public function __construct(
        public SelectComparison $condition,
        public SelectOperand $result,
    ) {}
}

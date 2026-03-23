<?php

declare(strict_types=1);

namespace SqlGen\Ast;

final readonly class SelectProjectionCase implements SelectProjection
{
    public function __construct(
        public SelectCaseExpression $expression,
        public ?SelectAlias $alias,
    ) {}
}

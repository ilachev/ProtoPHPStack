<?php

declare(strict_types=1);

namespace SqlGen\Ast;

final readonly class SelectProjectionFunction implements SelectProjection
{
    public function __construct(
        public SelectFunctionCall $function,
        public ?SelectAlias $alias,
    ) {}
}

<?php

declare(strict_types=1);

namespace SqlGen\Ast;

final readonly class SelectProjectionColumn implements SelectProjection
{
    public function __construct(
        public SelectColumnReference $reference,
        public ?SelectAlias $alias,
    ) {}
}

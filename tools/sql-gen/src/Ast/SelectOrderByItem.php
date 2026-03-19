<?php

declare(strict_types=1);

namespace SqlGen\Ast;

final readonly class SelectOrderByItem
{
    public function __construct(
        public SelectColumnReference $column,
        public ?string $direction,
    ) {}
}

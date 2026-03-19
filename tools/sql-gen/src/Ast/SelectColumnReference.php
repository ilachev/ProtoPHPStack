<?php

declare(strict_types=1);

namespace SqlGen\Ast;

final readonly class SelectColumnReference implements SelectOperand
{
    public function __construct(
        public ?string $table,
        public string $column,
    ) {}
}

<?php

declare(strict_types=1);

namespace SqlGen\Ast;

final readonly class SelectPlaceholder implements SelectOperand
{
    public function __construct(
        public string $name,
    ) {}
}

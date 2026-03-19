<?php

declare(strict_types=1);

namespace SqlGen\Ast;

final readonly class SelectTableReference
{
    public function __construct(
        public string $table,
        public ?string $alias,
    ) {}
}

<?php

declare(strict_types=1);

namespace SqlGen\Ast;

final readonly class SelectAlias
{
    public function __construct(
        public string $value,
    ) {}
}

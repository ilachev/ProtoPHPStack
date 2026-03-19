<?php

declare(strict_types=1);

namespace SqlGen\Schema;

final readonly class ResolvedParameterMapping
{
    public function __construct(
        public ?string $qualifier,
        public string $column,
        public string $parameterName,
    ) {}
}

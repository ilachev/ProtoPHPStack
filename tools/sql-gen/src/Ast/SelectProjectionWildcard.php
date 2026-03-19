<?php

declare(strict_types=1);

namespace SqlGen\Ast;

final readonly class SelectProjectionWildcard implements SelectProjection
{
    public function __construct(
        public ?string $table,
    ) {}
}

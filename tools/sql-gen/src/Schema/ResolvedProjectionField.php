<?php

declare(strict_types=1);

namespace SqlGen\Schema;

use Typhoon\Type;

final readonly class ResolvedProjectionField
{
    public function __construct(
        public string $sourceName,
        public string $resultColumn,
        public Type $phpType,
        public bool $nullable,
    ) {}
}

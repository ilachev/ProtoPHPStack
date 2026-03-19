<?php

declare(strict_types=1);

namespace SqlGen\Schema;

final readonly class ResolvedProjectionColumn
{
    public function __construct(
        public ?string $qualifier,
        public string $sourceColumn,
        public string $resultColumn,
    ) {}
}

<?php

declare(strict_types=1);

namespace SqlGen\Model;

final readonly class RowField
{
    public function __construct(
        public string $columnName,
        public string $propertyName,
        public string $phpType,
        public bool $nullable,
    ) {}
}

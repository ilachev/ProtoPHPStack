<?php

declare(strict_types=1);

namespace SqlGen\Model;

use Typhoon\Type;

final readonly class RowField
{
    public function __construct(
        public string $sourceColumnName,
        public string $resultColumnName,
        public string $propertyName,
        public Type $phpType,
        public bool $nullable,
    ) {}
}

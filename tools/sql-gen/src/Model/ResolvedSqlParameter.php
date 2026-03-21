<?php

declare(strict_types=1);

namespace SqlGen\Model;

use Typhoon\Type;

final readonly class ResolvedSqlParameter
{
    public function __construct(
        public string $name,
        public string $propertyName,
        public string $sqlType,
        public Type $phpType,
        public bool $nullable,
    ) {}
}

<?php

declare(strict_types=1);

namespace SqlGen\Model;

final readonly class ResolvedSqlParameter
{
    public function __construct(
        public string $name,
        public string $propertyName,
        public string $sqlType,
        public string $phpType,
        public bool $nullable,
    ) {}
}

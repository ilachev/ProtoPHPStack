<?php

declare(strict_types=1);

namespace SqlGen\Model;

final readonly class SchemaColumn
{
    public function __construct(
        public string $name,
        public string $sqlType,
        public string $phpType,
        public bool $nullable,
    ) {}
}

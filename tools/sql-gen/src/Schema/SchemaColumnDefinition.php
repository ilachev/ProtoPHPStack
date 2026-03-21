<?php

declare(strict_types=1);

namespace SqlGen\Schema;

final readonly class SchemaColumnDefinition
{
    public function __construct(
        public string $name,
        public string $sqlType,
        public bool $nullable,
        public bool $primaryKey = false,
        public bool $unique = false,
        public ?SchemaTableReferenceDefinition $reference = null,
    ) {}
}

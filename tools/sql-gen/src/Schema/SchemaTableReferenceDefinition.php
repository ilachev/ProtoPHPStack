<?php

declare(strict_types=1);

namespace SqlGen\Schema;

final readonly class SchemaTableReferenceDefinition
{
    public function __construct(
        public string $table,
        public string $column,
    ) {}
}

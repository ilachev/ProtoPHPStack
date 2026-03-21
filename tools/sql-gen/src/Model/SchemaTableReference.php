<?php

declare(strict_types=1);

namespace SqlGen\Model;

final readonly class SchemaTableReference
{
    public function __construct(
        public string $table,
        public string $column,
    ) {}
}

<?php

declare(strict_types=1);

namespace SqlGen\Schema;

use SqlGen\Model\SchemaColumn;
use SqlGen\Model\SchemaTable;

final readonly class ResolvedSchemaColumn
{
    public function __construct(
        public SchemaTable $table,
        public string $name,
        public SchemaColumn $column,
    ) {}
}

<?php

declare(strict_types=1);

namespace SqlGen\Model;

use Typhoon\Type;

final readonly class SchemaColumn
{
    public function __construct(
        public string $name,
        public string $sqlType,
        public Type $phpType,
        public bool $nullable,
        public bool $primaryKey = false,
        public bool $unique = false,
        public ?SchemaTableReference $reference = null,
    ) {}
}

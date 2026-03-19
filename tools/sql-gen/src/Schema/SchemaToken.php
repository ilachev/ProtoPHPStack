<?php

declare(strict_types=1);

namespace SqlGen\Schema;

final readonly class SchemaToken
{
    public function __construct(
        public string $type,
        public string $value,
    ) {}
}

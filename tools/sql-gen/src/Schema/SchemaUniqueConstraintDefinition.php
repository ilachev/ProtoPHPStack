<?php

declare(strict_types=1);

namespace SqlGen\Schema;

final readonly class SchemaUniqueConstraintDefinition
{
    /**
     * @param list<string> $columns
     */
    public function __construct(
        public array $columns,
    ) {}
}

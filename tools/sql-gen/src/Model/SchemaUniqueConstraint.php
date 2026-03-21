<?php

declare(strict_types=1);

namespace SqlGen\Model;

final readonly class SchemaUniqueConstraint
{
    /**
     * @param list<string> $columns
     */
    public function __construct(
        public array $columns,
    ) {}
}

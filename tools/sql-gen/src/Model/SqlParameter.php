<?php

declare(strict_types=1);

namespace SqlGen\Model;

final readonly class SqlParameter
{
    public function __construct(
        public string $name,
    ) {}
}

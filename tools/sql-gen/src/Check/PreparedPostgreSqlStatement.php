<?php

declare(strict_types=1);

namespace SqlGen\Check;

final readonly class PreparedPostgreSqlStatement
{
    /**
     * @param list<string> $parameterTypes
     */
    public function __construct(
        public string $name,
        public string $sql,
        public array $parameterTypes,
    ) {}
}

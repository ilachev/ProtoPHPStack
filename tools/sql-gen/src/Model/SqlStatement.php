<?php

declare(strict_types=1);

namespace SqlGen\Model;

final readonly class SqlStatement
{
    /**
     * @param list<SqlParameter> $parameters
     */
    public function __construct(
        public string $name,
        public SqlResultKind $resultKind,
        public string $sql,
        public array $parameters,
    ) {}
}

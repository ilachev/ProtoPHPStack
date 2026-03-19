<?php

declare(strict_types=1);

namespace SqlGen\Check;

final readonly class PostgreSqlConnectionConfig
{
    public function __construct(
        public string $host,
        public int $port,
        public string $database,
        public string $username,
        public string $password,
    ) {}
}

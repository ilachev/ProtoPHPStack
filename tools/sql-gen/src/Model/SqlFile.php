<?php

declare(strict_types=1);

namespace SqlGen\Model;

final readonly class SqlFile
{
    /**
     * @param list<SqlStatement> $statements
     */
    public function __construct(
        public string $sourcePath,
        public string $moduleName,
        public array $statements,
    ) {}
}

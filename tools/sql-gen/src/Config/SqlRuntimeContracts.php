<?php

declare(strict_types=1);

namespace SqlGen\Config;

final readonly class SqlRuntimeContracts
{
    public function __construct(
        public string $databaseRow,
        public string $executableQuery,
        public string $oneRowQuery,
        public string $manyRowsQuery,
        public string $rowReturningQuery,
    ) {}

    public static function fromNamespace(string $namespace): self
    {
        $namespace = trim($namespace, '\\');

        return new self(
            databaseRow: $namespace . '\DatabaseRow',
            executableQuery: $namespace . '\ExecutableQuery',
            oneRowQuery: $namespace . '\OneRowQuery',
            manyRowsQuery: $namespace . '\ManyRowsQuery',
            rowReturningQuery: $namespace . '\RowReturningQuery',
        );
    }

    public function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return $parts[array_key_last($parts)];
    }
}

<?php

declare(strict_types=1);

namespace App\Platform\Storage;

final readonly class SQLiteStorageConfig
{
    public function __construct(
        public string $database,
        public string $migrationsPath,
    ) {}

    /**
     * @param array{
     *     database: string,
     *     migrations_path: string,
     * } $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            database: $config['database'],
            migrationsPath: $config['migrations_path'],
        );
    }
}

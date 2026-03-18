<?php

declare(strict_types=1);

namespace App\Platform\Storage;

final readonly class StorageConfig
{
    public function __construct(
        public string $engine,
        public SQLiteStorageConfig $sqlite,
        public PostgreSqlStorageConfig $pgsql,
    ) {}

    /**
     * @param array{
     *     engine: string,
     *     sqlite: array{
     *         database: string,
     *         migrations_path: string,
     *     },
     *     pgsql: array{
     *         host: string,
     *         port: int,
     *         database: string,
     *         username: string,
     *         password: string,
     *         schema?: string,
     *         migrations_path: string,
     *     },
     * } $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            engine: $config['engine'],
            sqlite: SQLiteStorageConfig::fromArray($config['sqlite']),
            pgsql: PostgreSqlStorageConfig::fromArray($config['pgsql']),
        );
    }

    public function getMigrationsPath(): string
    {
        return match ($this->engine) {
            'sqlite' => $this->sqlite->migrationsPath,
            'pgsql' => $this->pgsql->migrationsPath,
            default => throw new StorageException("Unsupported storage engine: {$this->engine}"),
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Platform\Storage;

final readonly class PostgreSqlStorageConfig
{
    public function __construct(
        public string $host,
        public int $port,
        public string $database,
        public string $username,
        public string $password,
        public string $schema,
        public string $migrationsPath,
    ) {}

    /**
     * @param array{
     *     host: string,
     *     port: int,
     *     database: string,
     *     username: string,
     *     password: string,
     *     schema?: string,
     *     migrations_path: string,
     * } $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            host: $config['host'],
            port: $config['port'],
            database: $config['database'],
            username: $config['username'],
            password: $config['password'],
            schema: $config['schema'] ?? 'public',
            migrationsPath: $config['migrations_path'],
        );
    }
}

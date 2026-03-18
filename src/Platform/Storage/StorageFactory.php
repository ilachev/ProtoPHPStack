<?php

declare(strict_types=1);

namespace App\Platform\Storage;

use App\Platform\Logging\Logger;
use App\Platform\Storage\Query\QueryFactory;

/**
 * Factory for creating storage instances based on configuration.
 */
final readonly class StorageFactory
{
    public function __construct(
        private StorageConfig $config,
        private Logger $logger,
    ) {}

    /**
     * Create a storage implementation based on configuration.
     */
    public function createStorage(): Storage
    {
        $engine = $this->getEngine();

        $this->logger->info("Creating {$engine} storage");

        return match ($engine) {
            'sqlite' => $this->createSQLiteStorage(),
            'pgsql' => $this->createPostgreSQLStorage(),
            default => throw new StorageException("Unsupported storage engine: {$engine}"),
        };
    }

    /**
     * Create a query factory for the configured storage engine.
     */
    public function createQueryFactory(): QueryFactory
    {
        $engine = $this->getEngine();

        if ($engine === 'pgsql') {
            return new Query\PostgreSQLQueryFactory($this->config->pgsql->schema);
        }

        return new Query\SQLiteQueryFactory();
    }

    private function createSQLiteStorage(): SQLiteStorage
    {
        $databasePath = $this->config->sqlite->database;
        $databaseDir = \dirname($databasePath);

        if (!is_dir($databaseDir)) {
            mkdir($databaseDir, 0o755, true);
        }

        return new SQLiteStorage($databasePath);
    }

    private function createPostgreSQLStorage(): PostgreSQLStorage
    {
        $pgConfig = $this->config->pgsql;

        return new PostgreSQLStorage(
            host: $pgConfig->host,
            port: $pgConfig->port,
            dbname: $pgConfig->database,
            username: $pgConfig->username,
            password: $pgConfig->password,
            schema: $pgConfig->schema,
        );
    }

    public function getEngine(): string
    {
        return $this->config->engine;
    }
}

<?php

declare(strict_types=1);

namespace App\Platform\DI\ServiceProviders;

use App\Platform\Config\ProjectPath;
use App\Platform\DI\Container;
use App\Platform\DI\ServiceProvider;
use App\Platform\Logging\Logger;
use App\Platform\Storage\Query\QueryFactory;
use App\Platform\Storage\Sql\SqlExecutor;
use App\Platform\Storage\Storage;
use App\Platform\Storage\StorageConfig;
use App\Platform\Storage\StorageFactory;

/**
 * @implements ServiceProvider<object>
 */
final readonly class StorageServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        // Storage configuration
        $container->set(
            StorageConfig::class,
            static function (): StorageConfig {
                /** @var array{
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
                 *     }
                 * } $storageConfig
                 */
                $storageConfig = require ProjectPath::getConfigPath('storage.php');

                return StorageConfig::fromArray($storageConfig);
            },
        );

        // Storage factory
        $container->set(
            StorageFactory::class,
            static function (Container $container): StorageFactory {
                /** @var Logger $logger */
                $logger = $container->get(Logger::class);

                /** @var StorageConfig $storageConfig */
                $storageConfig = $container->get(StorageConfig::class);

                return new StorageFactory($storageConfig, $logger);
            },
        );

        // PostgreSQL storage implementation
        $container->set(
            Storage::class,
            static function (Container $container): Storage {
                /** @var StorageFactory $factory */
                $factory = $container->get(StorageFactory::class);

                return $factory->createStorage();
            },
        );

        // PostgreSQL query factory
        $container->set(
            QueryFactory::class,
            static function (Container $container): QueryFactory {
                /** @var StorageFactory $factory */
                $factory = $container->get(StorageFactory::class);

                return $factory->createQueryFactory();
            },
        );

        $container->set(
            SqlExecutor::class,
            static function (Container $container): SqlExecutor {
                /** @var Storage $storage */
                $storage = $container->get(Storage::class);

                return new SqlExecutor($storage);
            },
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Platform\DI\ServiceProviders;

use App\Platform\Config\ProjectPath;
use App\Platform\DI\Container;
use App\Platform\DI\ServiceProvider;
use App\Platform\Logging\Logger;
use App\Platform\Storage\Query\QueryFactory;
use App\Platform\Storage\Storage;
use App\Platform\Storage\StorageFactory;

/**
 * @implements ServiceProvider<object>
 */
final readonly class StorageServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        // Storage factory
        $container->set(
            StorageFactory::class,
            static function (Container $container): StorageFactory {
                /** @var array{
                 *     engine: string,
                 *     pgsql?: array{
                 *         host: string,
                 *         port: int,
                 *         database: string,
                 *         username: string,
                 *         password: string,
                 *         schema?: string
                 *     }
                 * } $storageConfig
                 */
                $storageConfig = require ProjectPath::getConfigPath('storage.php');

                /** @var Logger $logger */
                $logger = $container->get(Logger::class);

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
    }
}

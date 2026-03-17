<?php

declare(strict_types=1);

namespace App\Platform\DI\ServiceProviders;

use App\Platform\Cache\CacheConfig;
use App\Platform\Cache\CacheService;
use App\Platform\Cache\RoadRunnerCacheService;
use App\Platform\Config\ProjectPath;
use App\Platform\DI\Container;
use App\Platform\DI\ServiceProvider;

/**
 * @implements ServiceProvider<object>
 */
final readonly class CacheServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        // Cache config
        $container->set(
            CacheConfig::class,
            static function (): CacheConfig {
                /** @var array{
                 *     engine: string,
                 *     address: string,
                 *     default_prefix: string,
                 *     default_ttl: int,
                 *     serializer: int,
                 * } $cacheConfig
                 */
                $cacheConfig = require ProjectPath::getConfigPath('cache.php');

                return CacheConfig::fromArray($cacheConfig);
            },
        );

        // Cache service
        $container->bind(CacheService::class, RoadRunnerCacheService::class);
    }
}

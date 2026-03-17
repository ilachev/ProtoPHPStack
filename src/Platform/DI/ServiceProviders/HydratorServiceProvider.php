<?php

declare(strict_types=1);

namespace App\Platform\DI\ServiceProviders;

use App\Infrastructure\Hydrator\Hydrator;
use App\Infrastructure\Hydrator\LRUReflectionCache;
use App\Infrastructure\Hydrator\ProtobufHydration;
use App\Infrastructure\Hydrator\ReflectionCache;
use App\Infrastructure\Hydrator\ReflectionHydrator;
use App\Infrastructure\Hydrator\SetterProtobufHydration;
use App\Platform\DI\Container;
use App\Platform\DI\ServiceProvider;

/**
 * @implements ServiceProvider<object>
 */
final readonly class HydratorServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        // Register the primary hydrator contract.
        $container->bind(Hydrator::class, ReflectionHydrator::class);

        // Register the bounded reflection cache.
        $container->bind(ReflectionCache::class, LRUReflectionCache::class);
        $container->set(
            LRUReflectionCache::class,
            static fn() => new LRUReflectionCache(100),
        );

        // Register the protobuf hydration strategy.
        $container->bind(ProtobufHydration::class, SetterProtobufHydration::class);

        // Register the default reflection-based hydrator.
        $container->set(
            ReflectionHydrator::class,
            static function (Container $container): ReflectionHydrator {
                /** @var ReflectionCache $cache */
                $cache = $container->get(ReflectionCache::class);

                /** @var ProtobufHydration $protobufHydration */
                $protobufHydration = $container->get(ProtobufHydration::class);

                return new ReflectionHydrator($cache, $protobufHydration);
            },
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Platform\DI\ServiceProviders;

use App\Platform\DI\Container;
use App\Platform\DI\ServiceProvider;
use App\Platform\Hydration\Hydrator;
use App\Platform\Hydration\LRUReflectionCache;
use App\Platform\Hydration\ProtobufHydration;
use App\Platform\Hydration\ReflectionCache;
use App\Platform\Hydration\ReflectionHydrator;
use App\Platform\Hydration\SetterProtobufHydration;

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

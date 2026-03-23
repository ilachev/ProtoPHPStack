<?php

declare(strict_types=1);

use App\Capabilities\ApiStats\ApiStatsModule;
use App\Capabilities\Capability;
use App\Capabilities\Session\SessionModule;
use App\Platform\DI\Container;
use App\Platform\DI\ServiceProviders\CacheServiceProvider;
use App\Platform\DI\ServiceProviders\CoreServiceProvider;
use App\Platform\DI\ServiceProviders\HttpClientServiceProvider;
use App\Platform\DI\ServiceProviders\HydratorServiceProvider;
use App\Platform\DI\ServiceProviders\MigrationServiceProvider;
use App\Platform\DI\ServiceProviders\PlatformSupportServiceProvider;
use App\Platform\DI\ServiceProviders\RoutingServiceProvider;
use App\Platform\DI\ServiceProviders\StorageServiceProvider;

return static function (Container $container): void {
    $platformProviders = [
        new CoreServiceProvider(),
        new CacheServiceProvider(),
        new StorageServiceProvider(),
        new MigrationServiceProvider(),
        new HttpClientServiceProvider(),
        new RoutingServiceProvider(),
        new PlatformSupportServiceProvider(),
        new HydratorServiceProvider(),
    ];

    /** @var list<Capability<object>> $capabilities */
    $capabilities = [
        new ApiStatsModule(),
        new SessionModule(),
    ];

    foreach ($platformProviders as $provider) {
        $provider->register($container);
    }

    foreach ($capabilities as $capability) {
        $capability->register($container);
    }

    // Expose the container for components that resolve it explicitly.
    $container->set(Container::class, static fn() => $container);
};

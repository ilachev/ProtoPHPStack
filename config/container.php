<?php

declare(strict_types=1);

use App\Infrastructure\DI\Container;
use App\Infrastructure\DI\ServiceProviders\ApplicationServiceProvider;
use App\Infrastructure\DI\ServiceProviders\CacheServiceProvider;
use App\Infrastructure\DI\ServiceProviders\CoreServiceProvider;
use App\Infrastructure\DI\ServiceProviders\GeoLocationServiceProvider;
use App\Infrastructure\DI\ServiceProviders\HydratorServiceProvider;
use App\Infrastructure\DI\ServiceProviders\MigrationServiceProvider;
use App\Infrastructure\DI\ServiceProviders\RoutingServiceProvider;
use App\Infrastructure\DI\ServiceProviders\StorageServiceProvider;
use App\Modules\ApiStats\ApiStatsModule;
use App\Modules\Auth\AuthModule;
use App\Modules\Home\HomeModule;
use App\Modules\Module;
use App\Modules\Session\SessionModule;

return static function (Container $container): void {
    $platformProviders = [
        new CoreServiceProvider(),
        new CacheServiceProvider(),
        new StorageServiceProvider(),
        new GeoLocationServiceProvider(),
        new MigrationServiceProvider(),
        new RoutingServiceProvider(),
        new ApplicationServiceProvider(),
        new HydratorServiceProvider(),
    ];

    /** @var list<Module<object>> $modules */
    $modules = [
        new ApiStatsModule(),
        new AuthModule(),
        new HomeModule(),
        new SessionModule(),
    ];

    foreach ($platformProviders as $provider) {
        $provider->register($container);
    }

    foreach ($modules as $provider) {
        $provider->register($container);
    }

    // Set container reference
    $container->set(Container::class, static fn() => $container);
};

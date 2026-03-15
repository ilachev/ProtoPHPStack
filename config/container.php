<?php

declare(strict_types=1);

use App\Capabilities\ApiStats\ApiStatsModule;
use App\Capabilities\Capability;
use App\Capabilities\Session\SessionModule;
use App\Examples\Auth\AuthModule;
use App\Examples\ExampleModule;
use App\Examples\Home\HomeModule;
use App\Infrastructure\DI\Container;
use App\Infrastructure\DI\ServiceProviders\ApplicationServiceProvider;
use App\Infrastructure\DI\ServiceProviders\CacheServiceProvider;
use App\Infrastructure\DI\ServiceProviders\CoreServiceProvider;
use App\Infrastructure\DI\ServiceProviders\GeoLocationServiceProvider;
use App\Infrastructure\DI\ServiceProviders\HydratorServiceProvider;
use App\Infrastructure\DI\ServiceProviders\MigrationServiceProvider;
use App\Infrastructure\DI\ServiceProviders\RoutingServiceProvider;
use App\Infrastructure\DI\ServiceProviders\StorageServiceProvider;

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

    /** @var list<Capability<object>> $capabilities */
    $capabilities = [
        new ApiStatsModule(),
        new SessionModule(),
    ];

    /** @var list<ExampleModule<object>> $examples */
    $examples = [
        new AuthModule(),
        new HomeModule(),
    ];

    foreach ($platformProviders as $provider) {
        $provider->register($container);
    }

    foreach ($capabilities as $capability) {
        $capability->register($container);
    }

    foreach ($examples as $example) {
        $example->register($container);
    }

    // Set container reference
    $container->set(Container::class, static fn() => $container);
};

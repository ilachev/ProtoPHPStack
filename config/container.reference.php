<?php

declare(strict_types=1);

use App\Examples\Auth\AuthModule;
use App\Examples\ExampleModule;
use App\Examples\Home\HomeModule;
use App\Infrastructure\Config\ProjectPath;
use App\Infrastructure\DI\Container;
use App\Platform\Routing\RouteDefinition;
use App\Platform\Routing\RouteDefinitionInterface;

return static function (Container $container): void {
    /** @var callable(Container<object>): void $baseConfig */
    $baseConfig = require ProjectPath::getConfigPath('container.php');
    $baseConfig($container);

    /** @var list<ExampleModule<object>> $examples */
    $examples = [
        new AuthModule(),
        new HomeModule(),
    ];

    foreach ($examples as $example) {
        $example->register($container);
    }

    $container->set(
        RouteDefinitionInterface::class,
        static fn() => new RouteDefinition(ProjectPath::getConfigPath('routes.reference.php')),
    );
};

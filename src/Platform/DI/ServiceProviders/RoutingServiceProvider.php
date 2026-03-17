<?php

declare(strict_types=1);

namespace App\Platform\DI\ServiceProviders;

use App\Infrastructure\Config\ProjectPath;
use App\Platform\DI\Container;
use App\Platform\DI\ServiceProvider;
use App\Platform\Routing\RouteDefinition;
use App\Platform\Routing\RouteDefinitionInterface;
use App\Platform\Routing\Router;
use App\Platform\Routing\RouterInterface;

/**
 * @implements ServiceProvider<object>
 */
final readonly class RoutingServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        // Router
        $container->bind(RouterInterface::class, Router::class);

        // Route definition
        $container->set(
            RouteDefinitionInterface::class,
            static fn() => new RouteDefinition(ProjectPath::getConfigPath('routes.php')),
        );
    }
}

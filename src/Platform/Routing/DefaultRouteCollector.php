<?php

declare(strict_types=1);

namespace App\Platform\Routing;

final class DefaultRouteCollector implements RouteCollectorInterface
{
    /**
     * @var list<RouteEntry>
     */
    private array $routes = [];

    public function addRoute(string $method, string $path, string $handler, ?string $operationId = null): void
    {
        $this->routes[] = new RouteEntry(
            method: $method,
            path: $path,
            handler: $handler,
            operationId: $operationId,
        );
    }

    /**
     * @return list<RouteEntry>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}

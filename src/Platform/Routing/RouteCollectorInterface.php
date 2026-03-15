<?php

declare(strict_types=1);

namespace App\Platform\Routing;

interface RouteCollectorInterface
{
    public function addRoute(string $method, string $path, string $handler): void;
}

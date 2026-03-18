<?php

declare(strict_types=1);

namespace App\Platform\Routing\Generator;

use App\Platform\Http\GeneratedOperationManifestProvider;
use App\Platform\Routing\RouteEntry;

final readonly class GeneratedOperationRouteProvider implements RouteProvider
{
    public function __construct(
        private GeneratedOperationManifestProvider $operationProvider,
    ) {}

    public function getRoutes(): array
    {
        $routes = [];

        foreach ($this->operationProvider->getOperations() as $operation) {
            foreach ($operation->httpBindings as $binding) {
                $routes[] = new RouteEntry(
                    method: $binding->method,
                    path: $binding->path,
                    handler: $operation->handler,
                    operationId: $operation->operationId,
                );
            }
        }

        return $routes;
    }
}

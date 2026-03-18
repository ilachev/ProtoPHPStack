<?php

declare(strict_types=1);

namespace App\Platform\Routing\Generator;

use App\Platform\Http\GeneratedOperationManifestProvider;

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
                $routes[] = [
                    'method' => $binding->method,
                    'path' => $binding->path,
                    'handler' => $operation->handler,
                    'operation_id' => $operation->operationId,
                ];
            }
        }

        return $routes;
    }
}

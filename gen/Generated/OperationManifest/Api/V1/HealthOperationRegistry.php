<?php

declare(strict_types=1);

namespace App\Generated\OperationManifest\Api\V1;

use App\Platform\Http\Operation\HttpOperationBinding;
use App\Platform\Http\Operation\OperationDefinition;
use App\Platform\Http\Operation\OperationRegistry;

final readonly class HealthOperationRegistry implements OperationRegistry
{
    /**
     * @return list<OperationDefinition>
     */
    public function getOperations(): array
    {
        return [
                new OperationDefinition(
                    service: 'HealthService',
                    method: 'Check',
                    operationId: 'HealthService.Check',
                    requestClass: 'App\\Api\\V1\\HealthCheckRequest',
                    responseClass: 'App\\Api\\V1\\HealthCheckResponse',
                    handler: 'App\\Generated\\Endpoint\\Api\\V1\\HealthService\\CheckHttpHandler',
                    endpointInterface: 'App\\Generated\\Endpoint\\Api\\V1\\HealthService\\CheckEndpoint',
                    endpointImplementation: 'App\\Platform\\Http\\Endpoint\\Api\\V1\\HealthService\\CheckEndpoint',
                    httpBindings: [
                            new HttpOperationBinding(
                                method: 'GET',
                                path: '/api/v1/health',
                            )
                    ],
                )
        ];
    }
}

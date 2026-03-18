<?php

declare(strict_types=1);

namespace App\Platform\Http\Operation;

final readonly class OperationDefinition
{
    /**
     * @param class-string $requestClass
     * @param class-string $responseClass
     * @param class-string $handler
     * @param class-string $endpointInterface
     * @param class-string $endpointImplementation
     * @param list<HttpOperationBinding> $httpBindings
     */
    public function __construct(
        public string $service,
        public string $method,
        public string $operationId,
        public string $requestClass,
        public string $responseClass,
        public string $handler,
        public string $endpointInterface,
        public string $endpointImplementation,
        public array $httpBindings,
    ) {}
}

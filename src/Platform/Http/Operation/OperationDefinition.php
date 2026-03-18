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

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $service = $data['service'] ?? null;
        $method = $data['method'] ?? null;
        $operationId = $data['operation_id'] ?? null;
        $requestClass = $data['request_class'] ?? null;
        $responseClass = $data['response_class'] ?? null;
        $handler = $data['handler'] ?? null;
        $endpointInterface = $data['endpoint_interface'] ?? null;
        $endpointImplementation = $data['endpoint_implementation'] ?? null;
        $httpBindings = $data['http_bindings'] ?? null;

        if (
            !\is_string($service)
            || !\is_string($method)
            || !\is_string($operationId)
            || !\is_string($requestClass)
            || !\is_string($responseClass)
            || !\is_string($handler)
            || !\is_string($endpointInterface)
            || !\is_string($endpointImplementation)
            || !\is_array($httpBindings)
        ) {
            return null;
        }

        $normalizedBindings = [];

        foreach ($httpBindings as $binding) {
            if (!\is_array($binding)) {
                return null;
            }

            /** @var array<string, mixed> $binding */
            $operationBinding = HttpOperationBinding::fromArray($binding);
            if ($operationBinding === null) {
                return null;
            }

            $normalizedBindings[] = $operationBinding;
        }

        /** @var class-string $requestClass */
        /** @var class-string $responseClass */
        /** @var class-string $handler */
        /** @var class-string $endpointInterface */
        /** @var class-string $endpointImplementation */
        return new self(
            service: $service,
            method: $method,
            operationId: $operationId,
            requestClass: $requestClass,
            responseClass: $responseClass,
            handler: $handler,
            endpointInterface: $endpointInterface,
            endpointImplementation: $endpointImplementation,
            httpBindings: $normalizedBindings,
        );
    }
}

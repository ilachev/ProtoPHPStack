<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Http\Endpoint;

use App\Platform\Http\Endpoint\GeneratedEndpointBindingProvider;
use App\Platform\Http\Handler\HandlerInterface;
use App\Platform\Routing\Generator\GeneratedRouteManifestProvider;
use PHPUnit\Framework\TestCase;

final class GeneratedTransportSurfaceConsistencyTest extends TestCase
{
    public function testGeneratedRoutesHandlersAndEndpointBindingsStayConsistent(): void
    {
        $projectRoot = \dirname(__DIR__, 5);
        $routeProvider = new GeneratedRouteManifestProvider($projectRoot . '/gen/Generated/RouteManifest');
        $bindingProvider = new GeneratedEndpointBindingProvider($projectRoot . '/gen/Generated/EndpointBindings');
        $operations = $this->loadOperationManifest($projectRoot . '/gen/Generated/OperationManifest');
        $routesByOperation = $this->indexRoutesByOperationId($routeProvider->getRoutes());
        $bindings = $bindingProvider->getBindings();

        foreach ($operations as $operation) {
            $handlerClass = $operation['handler'];
            $operationId = $operation['operation_id'];
            $endpointInterface = $operation['endpoint_interface'];
            $implementation = $operation['endpoint_implementation'];
            $httpBindings = $operation['http_bindings'];

            self::assertTrue(class_exists($handlerClass), "Generated handler {$handlerClass} must exist");
            self::assertTrue(is_a($handlerClass, HandlerInterface::class, true), "{$handlerClass} must implement HandlerInterface");

            self::assertSame(
                $endpointInterface,
                $this->resolveEndpointInterface($handlerClass),
                "Generated handler {$handlerClass} must depend on {$endpointInterface}",
            );
            self::assertArrayHasKey($endpointInterface, $bindings, "Endpoint binding for {$endpointInterface} is missing");
            self::assertSame($implementation, $bindings[$endpointInterface]);
            self::assertTrue(class_exists($implementation), "Handwritten endpoint {$implementation} must exist");
            self::assertTrue(is_a($implementation, $endpointInterface, true), "{$implementation} must implement {$endpointInterface}");

            self::assertArrayHasKey($operationId, $routesByOperation, "Route entries for {$operationId} are missing");
            self::assertCount(
                \count($httpBindings),
                $routesByOperation[$operationId],
                "Route count for {$operationId} must match generated http bindings",
            );

            foreach ($httpBindings as $binding) {
                self::assertContains(
                    [
                        'method' => $binding['method'],
                        'path' => $binding['path'],
                        'handler' => $handlerClass,
                    ],
                    $routesByOperation[$operationId],
                    "Route surface for {$operationId} must contain {$binding['method']} {$binding['path']}",
                );
            }
        }
    }

    /**
     * @param class-string $handlerClass
     * @return class-string|null
     */
    private function resolveEndpointInterface(string $handlerClass): ?string
    {
        $constructor = (new \ReflectionClass($handlerClass))->getConstructor();
        if ($constructor === null) {
            return null;
        }

        $parameters = $constructor->getParameters();
        if ($parameters === []) {
            return null;
        }

        $type = $parameters[0]->getType();
        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        /** @var class-string $endpointInterface */
        $endpointInterface = $type->getName();

        return $endpointInterface;
    }

    /**
     * @param array<int|string, array{method: string, path: string, handler: string, operation_id?: string}> $routes
     * @return array<string, list<array{method: string, path: string, handler: string}>>
     */
    private function indexRoutesByOperationId(array $routes): array
    {
        $indexedRoutes = [];

        foreach ($routes as $route) {
            $operationId = $route['operation_id'] ?? null;
            if (!\is_string($operationId) || $operationId === '') {
                continue;
            }

            $indexedRoutes[$operationId][] = [
                'method' => $route['method'],
                'path' => $route['path'],
                'handler' => $route['handler'],
            ];
        }

        return $indexedRoutes;
    }

    /**
     * @return list<array{
     *     service: string,
     *     method: string,
     *     operation_id: string,
     *     request_class: class-string,
     *     response_class: class-string,
     *     handler: class-string,
     *     endpoint_interface: class-string,
     *     endpoint_implementation: class-string,
     *     http_bindings: list<array{method: string, path: string}>
     * }>
     */
    private function loadOperationManifest(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        $operations = [];

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }

            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $manifest = require $file->getPathname();
            if (!\is_array($manifest)) {
                continue;
            }

            foreach ($manifest as $operation) {
                if (!\is_array($operation)) {
                    continue;
                }

                $service = $operation['service'] ?? null;
                $method = $operation['method'] ?? null;
                $operationId = $operation['operation_id'] ?? null;
                $requestClass = $operation['request_class'] ?? null;
                $responseClass = $operation['response_class'] ?? null;
                $handler = $operation['handler'] ?? null;
                $endpointInterface = $operation['endpoint_interface'] ?? null;
                $endpointImplementation = $operation['endpoint_implementation'] ?? null;
                $httpBindings = $operation['http_bindings'] ?? null;

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
                    continue;
                }

                $normalizedBindings = [];

                foreach ($httpBindings as $binding) {
                    if (!\is_array($binding)) {
                        continue 2;
                    }

                    $bindingMethod = $binding['method'] ?? null;
                    $bindingPath = $binding['path'] ?? null;

                    if (!\is_string($bindingMethod) || !\is_string($bindingPath)) {
                        continue 2;
                    }

                    $normalizedBindings[] = [
                        'method' => $bindingMethod,
                        'path' => $bindingPath,
                    ];
                }

                /** @var class-string $requestClass */
                /** @var class-string $responseClass */
                /** @var class-string $handler */
                /** @var class-string $endpointInterface */
                /** @var class-string $endpointImplementation */
                $operations[] = [
                    'service' => $service,
                    'method' => $method,
                    'operation_id' => $operationId,
                    'request_class' => $requestClass,
                    'response_class' => $responseClass,
                    'handler' => $handler,
                    'endpoint_interface' => $endpointInterface,
                    'endpoint_implementation' => $endpointImplementation,
                    'http_bindings' => $normalizedBindings,
                ];
            }
        }

        return $operations;
    }
}

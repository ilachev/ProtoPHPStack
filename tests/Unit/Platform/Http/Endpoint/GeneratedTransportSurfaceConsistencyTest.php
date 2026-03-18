<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Http\Endpoint;

use App\Platform\Http\Endpoint\GeneratedEndpointImplementationMapProvider;
use App\Platform\Http\GeneratedOperationManifestProvider;
use App\Platform\Http\Handler\HandlerInterface;
use App\Platform\Routing\Generator\GeneratedOperationRouteProvider;
use PHPUnit\Framework\TestCase;

final class GeneratedTransportSurfaceConsistencyTest extends TestCase
{
    public function testGeneratedRoutesHandlersAndEndpointImplementationsStayConsistent(): void
    {
        $projectRoot = \dirname(__DIR__, 5);
        $operationProvider = new GeneratedOperationManifestProvider($projectRoot . '/gen/Generated/OperationManifest');
        $routeProvider = new GeneratedOperationRouteProvider($operationProvider);
        $implementationMapProvider = new GeneratedEndpointImplementationMapProvider($operationProvider);
        $operations = $operationProvider->getOperations();
        $routesByOperation = $this->indexRoutesByOperationId($routeProvider->getRoutes());
        $implementations = $implementationMapProvider->getImplementations();

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
            self::assertArrayHasKey($endpointInterface, $implementations, "Endpoint implementation for {$endpointInterface} is missing");
            self::assertSame($implementation, $implementations[$endpointInterface]);
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
}

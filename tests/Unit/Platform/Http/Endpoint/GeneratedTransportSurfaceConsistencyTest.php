<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Http\Endpoint;

use App\Platform\Http\Endpoint\GeneratedEndpointImplementationMapProvider;
use App\Platform\Http\GeneratedOperationManifestProvider;
use App\Platform\Http\Handler\HandlerInterface;
use App\Platform\Routing\Generator\GeneratedOperationRouteProvider;
use App\Platform\Routing\RouteEntry;
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
            $handlerClass = $operation->handler;
            $operationId = $operation->operationId;
            $endpointInterface = $operation->endpointInterface;
            $implementation = $operation->endpointImplementation;
            $httpBindings = $operation->httpBindings;

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
                self::assertTrue(
                    $this->hasRouteEntry(
                        $routesByOperation[$operationId],
                        $binding->method,
                        $binding->path,
                        $handlerClass,
                        $operationId,
                    ),
                    "Route surface for {$operationId} must contain {$binding->method} {$binding->path}",
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
     * @param list<RouteEntry> $routes
     * @return array<string, list<RouteEntry>>
     */
    private function indexRoutesByOperationId(array $routes): array
    {
        $indexedRoutes = [];

        foreach ($routes as $route) {
            $operationId = $route->operationId;
            if ($operationId === null || $operationId === '') {
                continue;
            }

            $indexedRoutes[$operationId][] = $route;
        }

        return $indexedRoutes;
    }

    /**
     * @param list<RouteEntry> $routes
     */
    private function hasRouteEntry(
        array $routes,
        string $method,
        string $path,
        string $handler,
        string $operationId,
    ): bool {
        foreach ($routes as $route) {
            if (
                $route->method === $method
                && $route->path === $path
                && $route->handler === $handler
                && $route->operationId === $operationId
            ) {
                return true;
            }
        }

        return false;
    }
}

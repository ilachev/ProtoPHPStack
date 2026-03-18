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
        $bindings = $bindingProvider->getBindings();

        foreach ($routeProvider->getRoutes() as $route) {
            $handlerClass = $route['handler'];
            $operationId = $route['operation_id'] ?? null;

            self::assertTrue(class_exists($handlerClass), "Generated handler {$handlerClass} must exist");
            self::assertTrue(is_a($handlerClass, HandlerInterface::class, true), "{$handlerClass} must implement HandlerInterface");

            $endpointInterface = $this->resolveEndpointInterface($handlerClass);
            self::assertNotNull($endpointInterface, "Generated handler {$handlerClass} must declare an endpoint interface dependency");
            self::assertTrue(interface_exists($endpointInterface), "Endpoint interface {$endpointInterface} must exist");
            self::assertArrayHasKey($endpointInterface, $bindings, "Endpoint binding for {$endpointInterface} is missing");

            $implementation = $bindings[$endpointInterface];
            self::assertTrue(class_exists($implementation), "Handwritten endpoint {$implementation} must exist");
            self::assertTrue(is_a($implementation, $endpointInterface, true), "{$implementation} must implement {$endpointInterface}");

            self::assertIsString($operationId);
            self::assertSame(
                $this->buildExpectedOperationId($endpointInterface),
                $operationId,
                "Route operation id must match generated endpoint interface {$endpointInterface}",
            );
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
     * @param class-string $endpointInterface
     */
    private function buildExpectedOperationId(string $endpointInterface): string
    {
        $parts = explode('\\', $endpointInterface);
        $serviceName = $parts[\count($parts) - 2] ?? '';
        $endpointShortName = $parts[\count($parts) - 1] ?? '';

        return $serviceName . '.' . substr($endpointShortName, 0, -\strlen('Endpoint'));
    }
}

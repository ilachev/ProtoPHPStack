<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Routing\Generator;

use App\Platform\Http\GeneratedOperationManifestProvider;
use App\Platform\Routing\Generator\GeneratedOperationRouteProvider;
use App\Platform\Routing\RouteEntry;
use PHPUnit\Framework\TestCase;

final class GeneratedOperationRouteProviderTest extends TestCase
{
    private string $tempDir;

    private string $registryNamespace;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/route_manifest_test_' . uniqid();
        $this->registryNamespace = 'Tests\Generated\OperationManifest\Registry' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testBuildsRoutesFromGeneratedOperationManifests(): void
    {
        $this->writeManifest(
            $this->tempDir . '/Api/V1/HealthOperationRegistry.php',
            $this->createRegistryContent(
                namespace: $this->registryNamespace . '\Api\V1',
                className: 'HealthOperationRegistry',
                service: 'HealthService',
                method: 'Check',
                operationId: 'HealthService.Check',
                requestClass: 'App\Api\V1\HealthCheckRequest',
                responseClass: 'App\Api\V1\HealthCheckResponse',
                handler: 'App\Generated\Endpoint\Api\V1\HealthService\CheckHttpHandler',
                endpointInterface: 'App\Generated\Endpoint\Api\V1\HealthService\CheckEndpoint',
                endpointImplementation: 'App\Platform\Http\Endpoint\Api\V1\HealthService\CheckEndpoint',
                httpMethod: 'GET',
                httpPath: '/api/v1/health',
            ),
        );

        $provider = new GeneratedOperationRouteProvider(
            new GeneratedOperationManifestProvider($this->tempDir, $this->registryNamespace),
        );
        $routes = $provider->getRoutes();

        self::assertCount(1, $routes);
        self::assertInstanceOf(RouteEntry::class, $routes[0]);
        self::assertSame('GET', $routes[0]->method);
        self::assertSame('/api/v1/health', $routes[0]->path);
        self::assertSame('App\Generated\Endpoint\Api\V1\HealthService\CheckHttpHandler', $routes[0]->handler);
        self::assertSame('HealthService.Check', $routes[0]->operationId);
    }

    public function testIgnoresMissingManifestDirectory(): void
    {
        $provider = new GeneratedOperationRouteProvider(
            new GeneratedOperationManifestProvider($this->tempDir . '/missing'),
        );

        self::assertSame([], $provider->getRoutes());
    }

    public function testLoadsManifestFilesRecursively(): void
    {
        $this->writeManifest(
            $this->tempDir . '/Api/V1/HealthOperationRegistry.php',
            $this->createRegistryContent(
                namespace: $this->registryNamespace . '\Api\V1',
                className: 'HealthOperationRegistry',
                service: 'HealthService',
                method: 'Check',
                operationId: 'HealthService.Check',
                requestClass: 'App\Api\V1\HealthCheckRequest',
                responseClass: 'App\Api\V1\HealthCheckResponse',
                handler: 'App\Generated\Endpoint\Api\V1\HealthService\CheckHttpHandler',
                endpointInterface: 'App\Generated\Endpoint\Api\V1\HealthService\CheckEndpoint',
                endpointImplementation: 'App\Platform\Http\Endpoint\Api\V1\HealthService\CheckEndpoint',
                httpMethod: 'GET',
                httpPath: '/api/v1/health',
            ),
        );
        $this->writeManifest(
            $this->tempDir . '/Api/V1/RuntimeInfoOperationRegistry.php',
            $this->createRegistryContent(
                namespace: $this->registryNamespace . '\Api\V1',
                className: 'RuntimeInfoOperationRegistry',
                service: 'RuntimeService',
                method: 'Describe',
                operationId: 'RuntimeService.Describe',
                requestClass: 'App\Api\V1\RuntimeDescribeRequest',
                responseClass: 'App\Api\V1\RuntimeDescribeResponse',
                handler: 'App\Generated\Endpoint\Api\V1\RuntimeService\DescribeHttpHandler',
                endpointInterface: 'App\Generated\Endpoint\Api\V1\RuntimeService\DescribeEndpoint',
                endpointImplementation: 'App\Platform\Http\Endpoint\Api\V1\RuntimeService\DescribeEndpoint',
                httpMethod: 'POST',
                httpPath: '/api/v1/runtime/info',
            ),
        );

        $provider = new GeneratedOperationRouteProvider(
            new GeneratedOperationManifestProvider($this->tempDir, $this->registryNamespace),
        );
        $routes = $provider->getRoutes();

        self::assertCount(2, $routes);
        self::assertSame(
            ['/api/v1/health', '/api/v1/runtime/info'],
            array_map(static fn(RouteEntry $route): string => $route->path, $routes),
        );
    }

    private function writeManifest(string $path, string $content): void
    {
        $directory = \dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0o777, true);
        }

        file_put_contents($path, $content);
    }

    private function createRegistryContent(
        string $namespace,
        string $className,
        string $service,
        string $method,
        string $operationId,
        string $requestClass,
        string $responseClass,
        string $handler,
        string $endpointInterface,
        string $endpointImplementation,
        string $httpMethod,
        string $httpPath,
    ): string {
        $template = <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace __NAMESPACE__;

            use App\Platform\Http\Operation\HttpOperationBinding;
            use App\Platform\Http\Operation\OperationDefinition;
            use App\Platform\Http\Operation\OperationRegistry;

            final readonly class __CLASS_NAME__ implements OperationRegistry
            {
                public function getOperations(): array
                {
                    return [
                        new OperationDefinition(
                            service: '__SERVICE__',
                            method: '__METHOD__',
                            operationId: '__OPERATION_ID__',
                            requestClass: '__REQUEST_CLASS__',
                            responseClass: '__RESPONSE_CLASS__',
                            handler: '__HANDLER__',
                            endpointInterface: '__ENDPOINT_INTERFACE__',
                            endpointImplementation: '__ENDPOINT_IMPLEMENTATION__',
                            httpBindings: [
                                new HttpOperationBinding(
                                    method: '__HTTP_METHOD__',
                                    path: '__HTTP_PATH__',
                                ),
                            ],
                        ),
                    ];
                }
            }
            PHP;

        return strtr($template, [
            '__NAMESPACE__' => $namespace,
            '__CLASS_NAME__' => $className,
            '__SERVICE__' => $service,
            '__METHOD__' => $method,
            '__OPERATION_ID__' => $operationId,
            '__REQUEST_CLASS__' => $requestClass,
            '__RESPONSE_CLASS__' => $responseClass,
            '__HANDLER__' => $handler,
            '__ENDPOINT_INTERFACE__' => $endpointInterface,
            '__ENDPOINT_IMPLEMENTATION__' => $endpointImplementation,
            '__HTTP_METHOD__' => $httpMethod,
            '__HTTP_PATH__' => $httpPath,
        ]);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }
}

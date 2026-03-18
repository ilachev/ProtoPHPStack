<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Routing\Generator;

use App\Platform\Http\GeneratedOperationManifestProvider;
use App\Platform\Routing\Generator\GeneratedOperationRouteProvider;
use PHPUnit\Framework\TestCase;

final class GeneratedOperationRouteProviderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/route_manifest_test_' . uniqid();
        mkdir($this->tempDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testBuildsRoutesFromGeneratedOperationManifests(): void
    {
        $this->writeManifest(
            $this->tempDir . '/app/v1/health.php',
            [
                [
                    'service' => 'HealthService',
                    'method' => 'Check',
                    'operation_id' => 'HealthService.Check',
                    'request_class' => 'App\Api\V1\HealthCheckRequest',
                    'response_class' => 'App\Api\V1\HealthCheckResponse',
                    'handler' => 'App\Generated\Endpoint\Api\V1\HealthService\CheckHttpHandler',
                    'endpoint_interface' => 'App\Generated\Endpoint\Api\V1\HealthService\CheckEndpoint',
                    'endpoint_implementation' => 'App\Platform\Http\Endpoint\Api\V1\HealthService\CheckEndpoint',
                    'http_bindings' => [
                        [
                            'method' => 'GET',
                            'path' => '/api/v1/health',
                        ],
                    ],
                ],
            ],
        );

        $provider = new GeneratedOperationRouteProvider(new GeneratedOperationManifestProvider($this->tempDir));
        $routes = $provider->getRoutes();

        self::assertCount(1, $routes);
        self::assertSame('GET', $routes[0]['method']);
        self::assertSame('/api/v1/health', $routes[0]['path']);
        self::assertSame('App\Generated\Endpoint\Api\V1\HealthService\CheckHttpHandler', $routes[0]['handler']);
        self::assertSame('HealthService.Check', $routes[0]['operation_id'] ?? null);
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
            $this->tempDir . '/app/v1/health.php',
            [
                [
                    'service' => 'HealthService',
                    'method' => 'Check',
                    'operation_id' => 'HealthService.Check',
                    'request_class' => 'App\Api\V1\HealthCheckRequest',
                    'response_class' => 'App\Api\V1\HealthCheckResponse',
                    'handler' => 'App\Generated\Endpoint\Api\V1\HealthService\CheckHttpHandler',
                    'endpoint_interface' => 'App\Generated\Endpoint\Api\V1\HealthService\CheckEndpoint',
                    'endpoint_implementation' => 'App\Platform\Http\Endpoint\Api\V1\HealthService\CheckEndpoint',
                    'http_bindings' => [
                        [
                            'method' => 'GET',
                            'path' => '/api/v1/health',
                        ],
                    ],
                ],
            ],
        );
        $this->writeManifest(
            $this->tempDir . '/app/v1/runtime/info.php',
            [
                [
                    'service' => 'RuntimeService',
                    'method' => 'Describe',
                    'operation_id' => 'RuntimeService.Describe',
                    'request_class' => 'App\Api\V1\RuntimeDescribeRequest',
                    'response_class' => 'App\Api\V1\RuntimeDescribeResponse',
                    'handler' => 'App\Generated\Endpoint\Api\V1\RuntimeService\DescribeHttpHandler',
                    'endpoint_interface' => 'App\Generated\Endpoint\Api\V1\RuntimeService\DescribeEndpoint',
                    'endpoint_implementation' => 'App\Platform\Http\Endpoint\Api\V1\RuntimeService\DescribeEndpoint',
                    'http_bindings' => [
                        [
                            'method' => 'POST',
                            'path' => '/api/v1/runtime/info',
                        ],
                    ],
                ],
            ],
        );

        $provider = new GeneratedOperationRouteProvider(new GeneratedOperationManifestProvider($this->tempDir));
        $routes = $provider->getRoutes();

        self::assertCount(2, $routes);
        self::assertSame(
            ['/api/v1/health', '/api/v1/runtime/info'],
            array_column($routes, 'path'),
        );
    }

    /**
     * @param list<array{
     *     service: string,
     *     method: string,
     *     operation_id: string,
     *     request_class: string,
     *     response_class: string,
     *     handler: string,
     *     endpoint_interface: string,
     *     endpoint_implementation: string,
     *     http_bindings: list<array{method: string, path: string}>
     * }> $routes
     */
    private function writeManifest(string $path, array $routes): void
    {
        $directory = \dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0o777, true);
        }

        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($routes, true) . ";\n";
        file_put_contents($path, $content);
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

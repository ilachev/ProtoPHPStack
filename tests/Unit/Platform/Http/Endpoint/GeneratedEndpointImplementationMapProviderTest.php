<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Http\Endpoint;

use App\Platform\Http\Endpoint\GeneratedEndpointImplementationMapProvider;
use App\Platform\Http\GeneratedOperationManifestProvider;
use PHPUnit\Framework\TestCase;

final class GeneratedEndpointImplementationMapProviderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/endpoint-implementations-' . bin2hex(random_bytes(8));
        mkdir($this->tempDir, recursive: true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->tempDir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tempDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }

            if ($file->isDir()) {
                rmdir($file->getPathname());

                continue;
            }

            unlink($file->getPathname());
        }

        rmdir($this->tempDir);
    }

    public function testLoadsAndMergesGeneratedEndpointImplementations(): void
    {
        $this->writeManifest(
            'app/v1/health.php',
            $this->createOperation(
                'HealthService',
                'Check',
                'HealthService.Check',
                'App\Api\V1\HealthCheckRequest',
                'App\Api\V1\HealthCheckResponse',
                'App\Generated\Transport\Api\V1\HealthService\CheckHttpHandler',
                'App\Generated\Transport\Api\V1\HealthService\CheckEndpoint',
                'App\Platform\Http\Endpoint\Api\V1\HealthService\CheckEndpoint',
                'GET',
                '/api/v1/health',
            ),
        );

        $this->writeManifest(
            'app/v1/system.php',
            $this->createOperation(
                'SystemService',
                'Describe',
                'SystemService.Describe',
                'App\Api\V1\SystemDescribeRequest',
                'App\Api\V1\SystemDescribeResponse',
                'App\Generated\Transport\Api\V1\SystemService\DescribeHttpHandler',
                'App\Generated\Transport\Api\V1\SystemService\DescribeEndpoint',
                'App\Platform\Http\Endpoint\Api\V1\SystemService\DescribeEndpoint',
                'POST',
                '/api/v1/system/describe',
            ),
        );

        $provider = new GeneratedEndpointImplementationMapProvider(new GeneratedOperationManifestProvider($this->tempDir));

        self::assertSame(
            [
                'App\Generated\Transport\Api\V1\HealthService\CheckEndpoint' => 'App\Platform\Http\Endpoint\Api\V1\HealthService\CheckEndpoint',
                'App\Generated\Transport\Api\V1\SystemService\DescribeEndpoint' => 'App\Platform\Http\Endpoint\Api\V1\SystemService\DescribeEndpoint',
            ],
            $provider->getImplementations(),
        );
    }

    public function testReturnsEmptyArrayWhenManifestDirectoryDoesNotExist(): void
    {
        $provider = new GeneratedEndpointImplementationMapProvider(
            new GeneratedOperationManifestProvider($this->tempDir . '/missing'),
        );

        self::assertSame([], $provider->getImplementations());
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
     * }> $operations
     */
    private function writeManifest(string $relativePath, array $operations): void
    {
        $fullPath = $this->tempDir . '/' . $relativePath;
        $directory = \dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, recursive: true);
        }

        file_put_contents(
            $fullPath,
            "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($operations, true) . ";\n",
        );
    }

    /**
     * @return list<array{
     *     service: string,
     *     method: string,
     *     operation_id: string,
     *     request_class: string,
     *     response_class: string,
     *     handler: string,
     *     endpoint_interface: string,
     *     endpoint_implementation: string,
     *     http_bindings: list<array{method: string, path: string}>
     * }>
     */
    private function createOperation(
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
    ): array {
        return [
            [
                'service' => $service,
                'method' => $method,
                'operation_id' => $operationId,
                'request_class' => $requestClass,
                'response_class' => $responseClass,
                'handler' => $handler,
                'endpoint_interface' => $endpointInterface,
                'endpoint_implementation' => $endpointImplementation,
                'http_bindings' => [
                    [
                        'method' => $httpMethod,
                        'path' => $httpPath,
                    ],
                ],
            ],
        ];
    }
}

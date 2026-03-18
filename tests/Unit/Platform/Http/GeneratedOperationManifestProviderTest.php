<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Http;

use App\Platform\Http\GeneratedOperationManifestProvider;
use App\Platform\Http\Operation\HttpOperationBinding;
use App\Platform\Http\Operation\OperationDefinition;
use PHPUnit\Framework\TestCase;

final class GeneratedOperationManifestProviderTest extends TestCase
{
    private string $tempDir;

    private string $registryNamespace;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/operation-manifest-' . bin2hex(random_bytes(8));
        $this->registryNamespace = 'Tests\Generated\OperationManifest\Registry' . bin2hex(random_bytes(6));
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

    public function testLoadsAndNormalizesGeneratedOperationManifests(): void
    {
        $this->writeManifest(
            'Api/V1/HealthOperationRegistry.php',
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

        $provider = new GeneratedOperationManifestProvider($this->tempDir, $this->registryNamespace);

        self::assertEquals(
            [
                new OperationDefinition(
                    service: 'HealthService',
                    method: 'Check',
                    operationId: 'HealthService.Check',
                    requestClass: 'App\Api\V1\HealthCheckRequest',
                    responseClass: 'App\Api\V1\HealthCheckResponse',
                    handler: 'App\Generated\Endpoint\Api\V1\HealthService\CheckHttpHandler',
                    endpointInterface: 'App\Generated\Endpoint\Api\V1\HealthService\CheckEndpoint',
                    endpointImplementation: 'App\Platform\Http\Endpoint\Api\V1\HealthService\CheckEndpoint',
                    httpBindings: [
                        new HttpOperationBinding(
                            method: 'GET',
                            path: '/api/v1/health',
                        ),
                    ],
                ),
            ],
            $provider->getOperations(),
        );
    }

    public function testReturnsEmptyArrayWhenManifestDirectoryDoesNotExist(): void
    {
        $provider = new GeneratedOperationManifestProvider($this->tempDir . '/missing');

        self::assertSame([], $provider->getOperations());
    }

    private function writeManifest(string $relativePath, string $content): void
    {
        $fullPath = $this->tempDir . '/' . $relativePath;
        $directory = \dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, recursive: true);
        }

        file_put_contents($fullPath, $content);
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
}

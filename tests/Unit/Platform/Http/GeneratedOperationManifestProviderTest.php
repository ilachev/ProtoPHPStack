<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Http;

use App\Platform\Http\GeneratedOperationManifestProvider;
use PHPUnit\Framework\TestCase;

final class GeneratedOperationManifestProviderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/operation-manifest-' . bin2hex(random_bytes(8));
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
            'app/v1/health.php',
            [
                [
                    'service' => 'HealthService',
                    'method' => 'Check',
                    'operation_id' => 'HealthService.Check',
                    'request_class' => 'App\Api\V1\HealthCheckRequest',
                    'response_class' => 'App\Api\V1\HealthCheckResponse',
                    'handler' => 'App\Generated\Transport\Api\V1\HealthService\CheckHttpHandler',
                    'endpoint_interface' => 'App\Generated\Transport\Api\V1\HealthService\CheckEndpoint',
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

        $provider = new GeneratedOperationManifestProvider($this->tempDir);

        self::assertSame(
            [
                [
                    'service' => 'HealthService',
                    'method' => 'Check',
                    'operation_id' => 'HealthService.Check',
                    'request_class' => 'App\Api\V1\HealthCheckRequest',
                    'response_class' => 'App\Api\V1\HealthCheckResponse',
                    'handler' => 'App\Generated\Transport\Api\V1\HealthService\CheckHttpHandler',
                    'endpoint_interface' => 'App\Generated\Transport\Api\V1\HealthService\CheckEndpoint',
                    'endpoint_implementation' => 'App\Platform\Http\Endpoint\Api\V1\HealthService\CheckEndpoint',
                    'http_bindings' => [
                        [
                            'method' => 'GET',
                            'path' => '/api/v1/health',
                        ],
                    ],
                ],
            ],
            $provider->getOperations(),
        );
    }

    public function testReturnsEmptyArrayWhenManifestDirectoryDoesNotExist(): void
    {
        $provider = new GeneratedOperationManifestProvider($this->tempDir . '/missing');

        self::assertSame([], $provider->getOperations());
    }

    /**
     * @param list<array{
     *     service: string,
     *     method: string,
     *     operation_id: string,
     *     request_class: class-string,
     *     response_class: class-string,
     *     handler: class-string,
     *     endpoint_interface: class-string,
     *     endpoint_implementation: class-string,
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
}

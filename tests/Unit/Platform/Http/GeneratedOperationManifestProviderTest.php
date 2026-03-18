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
            "<?php\n\ndeclare(strict_types=1);\n\nuse App\\Platform\\Http\\Operation\\HttpOperationBinding;\nuse App\\Platform\\Http\\Operation\\OperationDefinition;\n\nreturn [\n    new OperationDefinition(\n        service: 'HealthService',\n        method: 'Check',\n        operationId: 'HealthService.Check',\n        requestClass: 'App\\Api\\V1\\HealthCheckRequest',\n        responseClass: 'App\\Api\\V1\\HealthCheckResponse',\n        handler: 'App\\Generated\\Endpoint\\Api\\V1\\HealthService\\CheckHttpHandler',\n        endpointInterface: 'App\\Generated\\Endpoint\\Api\\V1\\HealthService\\CheckEndpoint',\n        endpointImplementation: 'App\\Platform\\Http\\Endpoint\\Api\\V1\\HealthService\\CheckEndpoint',\n        httpBindings: [\n            new HttpOperationBinding(\n                method: 'GET',\n                path: '/api/v1/health',\n            ),\n        ],\n    ),\n];\n",
        );

        $provider = new GeneratedOperationManifestProvider($this->tempDir);

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
}

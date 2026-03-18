<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Routing\Generator;

use App\Platform\Routing\Generator\GeneratedRouteManifestProvider;
use PHPUnit\Framework\TestCase;

final class GeneratedRouteManifestProviderTest extends TestCase
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

    public function testLoadsRoutesFromGeneratedManifestFiles(): void
    {
        $this->writeManifest(
            $this->tempDir . '/app/v1/health.php',
            [
                [
                    'method' => 'GET',
                    'path' => '/api/v1/health',
                    'handler' => 'App\Generated\Transport\Api\V1\HealthService\CheckHttpHandler',
                    'operation_id' => 'HealthService.Check',
                ],
            ],
        );

        $provider = new GeneratedRouteManifestProvider($this->tempDir);
        $routes = $provider->getRoutes();

        self::assertCount(1, $routes);
        self::assertSame('GET', $routes[0]['method']);
        self::assertSame('/api/v1/health', $routes[0]['path']);
        self::assertSame('App\Generated\Transport\Api\V1\HealthService\CheckHttpHandler', $routes[0]['handler']);
        self::assertSame('HealthService.Check', $routes[0]['operation_id'] ?? null);
    }

    public function testIgnoresMissingManifestDirectory(): void
    {
        $provider = new GeneratedRouteManifestProvider($this->tempDir . '/missing');

        self::assertSame([], $provider->getRoutes());
    }

    public function testLoadsManifestFilesRecursively(): void
    {
        $this->writeManifest(
            $this->tempDir . '/app/v1/health.php',
            [
                [
                    'method' => 'GET',
                    'path' => '/api/v1/health',
                    'handler' => 'App\Generated\Transport\Api\V1\HealthService\CheckHttpHandler',
                ],
            ],
        );
        $this->writeManifest(
            $this->tempDir . '/app/v1/runtime/info.php',
            [
                [
                    'method' => 'POST',
                    'path' => '/api/v1/runtime/info',
                    'handler' => 'App\Generated\Transport\Api\V1\RuntimeService\DescribeHttpHandler',
                ],
            ],
        );

        $provider = new GeneratedRouteManifestProvider($this->tempDir);
        $routes = $provider->getRoutes();

        self::assertCount(2, $routes);
        self::assertSame(
            ['/api/v1/health', '/api/v1/runtime/info'],
            array_column($routes, 'path'),
        );
    }

    /**
     * @param list<array{method: string, path: string, handler: string, operation_id?: string}> $routes
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

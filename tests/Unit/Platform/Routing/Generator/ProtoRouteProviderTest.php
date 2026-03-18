<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Routing\Generator;

use App\Platform\Routing\Generator\ProtoRouteProvider;
use PHPUnit\Framework\TestCase;

final class ProtoRouteProviderTest extends TestCase
{
    private string $tempDir;

    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/proto_test_' . uniqid();
        mkdir($this->tempDir, 0o777, true);
        $this->fixtureDir = \dirname(__DIR__, 5) . '/protos/gen/App/Api/V1/Metadata';
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = "{$dir}/{$file}";
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function copyMetadataFixture(string $fixtureName, string $targetFile): void
    {
        $sourceFile = $this->fixtureDir . '/' . $fixtureName;
        $dir = \dirname($targetFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0o777, true);
        }

        copy($sourceFile, $targetFile);
    }

    public function testGetRoutesWithSingleServiceSingleMethod(): void
    {
        $this->copyMetadataFixture('Health.php', "{$this->tempDir}/Health.php");

        $provider = new ProtoRouteProvider($this->tempDir);
        $routes = $provider->getRoutes();

        self::assertCount(1, $routes);
        self::assertEquals('GET', $routes[0]['method']);
        self::assertEquals('/api/v1/health', $routes[0]['path']);
        self::assertEquals('App\Generated\Transport\Api\V1\HealthService\CheckHttpHandler', $routes[0]['handler']);
        self::assertArrayHasKey('operation_id', $routes[0]);
        $operationId = $routes[0]['operation_id'] ?? null;
        self::assertEquals('HealthService.Check', $operationId);
    }

    public function testGetRoutesCanFilterByDescriptorSourcePrefix(): void
    {
        $this->copyMetadataFixture('Health.php', "{$this->tempDir}/Health.php");

        $provider = new ProtoRouteProvider(
            $this->tempDir,
            ['app/v1/'],
        );
        $routes = $provider->getRoutes();

        self::assertCount(1, $routes);
        self::assertSame('GET', $routes[0]['method']);
        self::assertSame('/api/v1/health', $routes[0]['path']);
        self::assertSame('App\Generated\Transport\Api\V1\HealthService\CheckHttpHandler', $routes[0]['handler']);
        self::assertSame('HealthService.Check', $routes[0]['operation_id'] ?? null);
    }

    public function testGetRoutesWithEmptyDirectory(): void
    {
        $emptyDir = "{$this->tempDir}/empty";
        mkdir($emptyDir);

        $provider = new ProtoRouteProvider($emptyDir);
        $routes = $provider->getRoutes();

        self::assertEmpty($routes);
    }

    public function testGetRoutesWithNestedDirectories(): void
    {
        $this->copyMetadataFixture('Health.php', "{$this->tempDir}/v1/Health.php");
        $this->copyMetadataFixture('Health.php', "{$this->tempDir}/v2/Health.php");

        $provider = new ProtoRouteProvider($this->tempDir);
        $routes = $provider->getRoutes();

        self::assertCount(2, $routes);

        $paths = array_column($routes, 'path');
        self::assertSame(['/api/v1/health', '/api/v1/health'], $paths);
    }

    public function testGetRoutesWithNoValidProtoServiceDefinition(): void
    {
        file_put_contents(
            "{$this->tempDir}/Invalid.php",
            <<<'PHP'
                <?php

                declare(strict_types=1);

                final class InvalidMetadata
                {
                }
                PHP,
        );

        $provider = new ProtoRouteProvider($this->tempDir);
        $routes = $provider->getRoutes();

        self::assertEmpty($routes);
    }
}

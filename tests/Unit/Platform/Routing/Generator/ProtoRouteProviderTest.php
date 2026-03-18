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
        $this->copyMetadataFixture('Home.php', "{$this->tempDir}/Home.php");

        $provider = new ProtoRouteProvider($this->tempDir);
        $routes = $provider->getRoutes();

        self::assertCount(1, $routes);
        self::assertEquals('GET', $routes[0]['method']);
        self::assertEquals('/api/v1/home', $routes[0]['path']);
        self::assertEquals('App\Examples\Home\Transport\Http\HomeHandler', $routes[0]['handler']);
        self::assertArrayHasKey('operation_id', $routes[0]);
        $operationId = $routes[0]['operation_id'] ?? null;
        self::assertEquals('HomeService.Home', $operationId);
    }

    public function testGetRoutesWithCustomMapping(): void
    {
        $this->copyMetadataFixture('Home.php', "{$this->tempDir}/Home.php");

        $handlerMapping = [
            'HomeService.Home' => 'App\Examples\Custom\Transport\Http\SpecialHandler',
        ];

        $provider = new ProtoRouteProvider($this->tempDir, $handlerMapping);
        $routes = $provider->getRoutes();

        self::assertCount(1, $routes);
        self::assertEquals('GET', $routes[0]['method']);
        self::assertEquals('/api/v1/home', $routes[0]['path']);
        self::assertEquals('App\Examples\Custom\Transport\Http\SpecialHandler', $routes[0]['handler']);
    }

    public function testGetRoutesWithMultipleHttpMethods(): void
    {
        $this->copyMetadataFixture('Auth.php', "{$this->tempDir}/Auth.php");

        $provider = new ProtoRouteProvider($this->tempDir);
        $routes = $provider->getRoutes();

        self::assertCount(3, $routes);

        $httpMethods = array_column($routes, 'method');
        $paths = array_column($routes, 'path');

        self::assertSame(['POST', 'POST', 'POST'], $httpMethods);
        self::assertSame(
            [
                '/api/v1/auth/login',
                '/api/v1/auth/logout',
                '/api/v1/auth/refresh',
            ],
            $paths,
        );
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
        $this->copyMetadataFixture('Home.php', "{$this->tempDir}/v1/Home.php");
        $this->copyMetadataFixture('Auth.php', "{$this->tempDir}/v2/Auth.php");

        $provider = new ProtoRouteProvider($this->tempDir);
        $routes = $provider->getRoutes();

        self::assertCount(4, $routes);

        $paths = array_column($routes, 'path');
        self::assertContains('/api/v1/home', $paths);
        self::assertContains('/api/v1/auth/login', $paths);
        self::assertContains('/api/v1/auth/logout', $paths);
        self::assertContains('/api/v1/auth/refresh', $paths);
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

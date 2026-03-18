<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Http\Endpoint;

use App\Platform\Http\Endpoint\GeneratedEndpointBindingProvider;
use PHPUnit\Framework\TestCase;

final class GeneratedEndpointBindingProviderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/endpoint-bindings-' . bin2hex(random_bytes(8));
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

    public function testLoadsAndMergesGeneratedEndpointBindings(): void
    {
        $this->writeManifest(
            'app/v1/health.php',
            $this->createBindings(
                'App\Generated\Transport\Api\V1\HealthService\CheckEndpoint',
                'App\Platform\Http\Endpoint\Api\V1\HealthService\CheckEndpoint',
            ),
        );

        $this->writeManifest(
            'app/v1/system.php',
            $this->createBindings(
                'App\Generated\Transport\Api\V1\SystemService\DescribeEndpoint',
                'App\Platform\Http\Endpoint\Api\V1\SystemService\DescribeEndpoint',
            ),
        );

        $provider = new GeneratedEndpointBindingProvider($this->tempDir);

        self::assertSame(
            [
                'App\Generated\Transport\Api\V1\HealthService\CheckEndpoint' => 'App\Platform\Http\Endpoint\Api\V1\HealthService\CheckEndpoint',
                'App\Generated\Transport\Api\V1\SystemService\DescribeEndpoint' => 'App\Platform\Http\Endpoint\Api\V1\SystemService\DescribeEndpoint',
            ],
            $provider->getBindings(),
        );
    }

    public function testReturnsEmptyArrayWhenManifestDirectoryDoesNotExist(): void
    {
        $provider = new GeneratedEndpointBindingProvider($this->tempDir . '/missing');

        self::assertSame([], $provider->getBindings());
    }

    /**
     * @param array<string, string> $bindings
     */
    private function writeManifest(string $relativePath, array $bindings): void
    {
        $fullPath = $this->tempDir . '/' . $relativePath;
        $directory = \dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, recursive: true);
        }

        file_put_contents(
            $fullPath,
            "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($bindings, true) . ";\n",
        );
    }

    /**
     * @return array<string, string>
     */
    private function createBindings(string $interface, string $implementation): array
    {
        return [
            $interface => $implementation,
        ];
    }
}

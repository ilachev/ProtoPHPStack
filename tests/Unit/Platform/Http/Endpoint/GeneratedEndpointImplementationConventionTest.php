<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Http\Endpoint;

use App\Platform\Http\Endpoint\EndpointImplementationResolver;
use App\Platform\Http\Endpoint\GeneratedEndpointImplementationMapProvider;
use App\Platform\Http\GeneratedOperationManifestProvider;
use PHPUnit\Framework\TestCase;

final class GeneratedEndpointImplementationConventionTest extends TestCase
{
    public function testEveryGeneratedEndpointHasAHandwrittenImplementation(): void
    {
        $resolver = new EndpointImplementationResolver(
            new GeneratedEndpointImplementationMapProvider(
                new GeneratedOperationManifestProvider(\dirname(__DIR__, 5) . '/gen/Generated/OperationManifest'),
            ),
        );

        foreach ($this->findGeneratedEndpointInterfaces() as $interface) {
            $implementation = $resolver->resolve($interface);

            self::assertNotNull($implementation, "Missing implementation for {$interface}");
            self::assertTrue(class_exists($implementation), "Implementation class {$implementation} does not exist");
            self::assertTrue(is_a($implementation, $interface, true), "{$implementation} must implement {$interface}");
        }
    }

    /**
     * @return list<class-string>
     */
    private function findGeneratedEndpointInterfaces(): array
    {
        $directory = \dirname(__DIR__, 5) . '/gen/Generated/Transport';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        $interfaces = [];

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }

            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            if (!str_ends_with($path, 'Endpoint.php') || str_ends_with($path, 'HttpHandler.php')) {
                continue;
            }

            $relativePath = substr($path, \strlen($directory) + 1);
            /** @var class-string $className */
            $className = 'App\Generated\Transport\\' . str_replace('/', '\\', substr($relativePath, 0, -4));
            $interfaces[] = $className;
        }

        sort($interfaces);

        return $interfaces;
    }
}

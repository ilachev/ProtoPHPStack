<?php

declare(strict_types=1);

namespace App\Platform\Routing\Generator;

final readonly class GeneratedRouteManifestProvider implements RouteProvider
{
    public function __construct(
        private string $manifestDir,
    ) {}

    public function getRoutes(): array
    {
        $routes = [];

        foreach ($this->findManifestFiles() as $manifestFile) {
            $manifest = require $manifestFile;
            if (!\is_array($manifest)) {
                continue;
            }

            foreach ($manifest as $route) {
                if (!\is_array($route)) {
                    continue;
                }

                $method = $route['method'] ?? null;
                $path = $route['path'] ?? null;
                $handler = $route['handler'] ?? null;
                $operationId = $route['operation_id'] ?? null;

                if (!\is_string($method) || !\is_string($path) || !\is_string($handler)) {
                    continue;
                }

                $normalizedRoute = [
                    'method' => $method,
                    'path' => $path,
                    'handler' => $handler,
                ];

                if (\is_string($operationId) && $operationId !== '') {
                    $normalizedRoute['operation_id'] = $operationId;
                }

                $routes[] = $normalizedRoute;
            }
        }

        return $routes;
    }

    /**
     * @return list<string>
     */
    private function findManifestFiles(): array
    {
        if (!is_dir($this->manifestDir)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->manifestDir));
        $files = [];

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }

            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
    }
}

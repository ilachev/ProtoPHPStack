<?php

declare(strict_types=1);

namespace App\Platform\Http\Endpoint;

final class GeneratedEndpointBindingProvider
{
    /**
     * @var array<class-string, class-string>|null
     */
    private ?array $bindings = null;

    public function __construct(
        private readonly string $manifestDir,
    ) {}

    /**
     * @return array<class-string, class-string>
     */
    public function getBindings(): array
    {
        if ($this->bindings !== null) {
            return $this->bindings;
        }

        $bindings = [];

        foreach ($this->findManifestFiles() as $manifestFile) {
            $manifest = require $manifestFile;
            if (!\is_array($manifest)) {
                continue;
            }

            foreach ($manifest as $interface => $implementation) {
                if (!\is_string($interface) || !\is_string($implementation)) {
                    continue;
                }

                /** @var class-string $interface */
                /** @var class-string $implementation */
                $bindings[$interface] = $implementation;
            }
        }

        ksort($bindings);
        $this->bindings = $bindings;

        return $this->bindings;
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

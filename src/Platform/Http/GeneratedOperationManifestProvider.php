<?php

declare(strict_types=1);

namespace App\Platform\Http;

use App\Platform\Http\Operation\OperationDefinition;
use App\Platform\Http\Operation\OperationRegistry;

final class GeneratedOperationManifestProvider
{
    private const DEFAULT_REGISTRY_NAMESPACE = 'App\Generated\OperationManifest';

    /**
     * @var list<OperationDefinition>|null
     */
    private ?array $operations = null;

    public function __construct(
        private readonly string $manifestDir,
        private readonly string $registryNamespace = self::DEFAULT_REGISTRY_NAMESPACE,
    ) {}

    /**
     * @return list<OperationDefinition>
     */
    public function getOperations(): array
    {
        if ($this->operations !== null) {
            return $this->operations;
        }

        $operations = [];

        foreach ($this->findRegistryClasses() as $registryClass => $registryFile) {
            require_once $registryFile;

            if (!class_exists($registryClass) || !is_a($registryClass, OperationRegistry::class, true)) {
                continue;
            }

            /** @var OperationRegistry $registry */
            $registry = new $registryClass();

            foreach ($registry->getOperations() as $operation) {
                $operations[] = $operation;
            }
        }

        return $this->operations = $operations;
    }

    /**
     * @return array<string, string>
     */
    private function findRegistryClasses(): array
    {
        if (!is_dir($this->manifestDir)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->manifestDir));
        $registries = [];

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }

            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            $relativePath = substr($path, \strlen(rtrim($this->manifestDir, '/')) + 1);
            $relativeClass = substr($relativePath, 0, -4);
            if ($relativeClass === '') {
                continue;
            }
            $className = rtrim($this->registryNamespace, '\\') . '\\' . str_replace('/', '\\', $relativeClass);
            $registries[$className] = $path;
        }

        ksort($registries);

        return $registries;
    }
}

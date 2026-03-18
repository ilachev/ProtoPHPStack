<?php

declare(strict_types=1);

namespace App\Platform\Http;

use App\Platform\Http\Operation\OperationDefinition;

final class GeneratedOperationManifestProvider
{
    /**
     * @var list<OperationDefinition>|null
     */
    private ?array $operations = null;

    public function __construct(
        private readonly string $manifestDir,
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

        foreach ($this->findManifestFiles() as $manifestFile) {
            $manifest = require $manifestFile;
            if (!\is_array($manifest)) {
                continue;
            }

            foreach ($manifest as $operation) {
                if (!\is_array($operation)) {
                    continue;
                }

                /** @var array<string, mixed> $operation */
                $operationDefinition = OperationDefinition::fromArray($operation);
                if ($operationDefinition === null) {
                    continue;
                }

                $operations[] = $operationDefinition;
            }
        }

        return $this->operations = $operations;
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

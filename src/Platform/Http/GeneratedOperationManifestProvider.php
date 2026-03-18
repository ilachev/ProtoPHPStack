<?php

declare(strict_types=1);

namespace App\Platform\Http;

final class GeneratedOperationManifestProvider
{
    /**
     * @var list<array{
     *     service: string,
     *     method: string,
     *     operation_id: string,
     *     request_class: class-string,
     *     response_class: class-string,
     *     handler: class-string,
     *     endpoint_interface: class-string,
     *     endpoint_implementation: class-string,
     *     http_bindings: list<array{method: string, path: string}>
     * }>|null
     */
    private ?array $operations = null;

    public function __construct(
        private readonly string $manifestDir,
    ) {}

    /**
     * @return list<array{
     *     service: string,
     *     method: string,
     *     operation_id: string,
     *     request_class: class-string,
     *     response_class: class-string,
     *     handler: class-string,
     *     endpoint_interface: class-string,
     *     endpoint_implementation: class-string,
     *     http_bindings: list<array{method: string, path: string}>
     * }>
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

                $service = $operation['service'] ?? null;
                $method = $operation['method'] ?? null;
                $operationId = $operation['operation_id'] ?? null;
                $requestClass = $operation['request_class'] ?? null;
                $responseClass = $operation['response_class'] ?? null;
                $handler = $operation['handler'] ?? null;
                $endpointInterface = $operation['endpoint_interface'] ?? null;
                $endpointImplementation = $operation['endpoint_implementation'] ?? null;
                $httpBindings = $operation['http_bindings'] ?? null;

                if (
                    !\is_string($service)
                    || !\is_string($method)
                    || !\is_string($operationId)
                    || !\is_string($requestClass)
                    || !\is_string($responseClass)
                    || !\is_string($handler)
                    || !\is_string($endpointInterface)
                    || !\is_string($endpointImplementation)
                    || !\is_array($httpBindings)
                ) {
                    continue;
                }

                $normalizedBindings = [];

                foreach ($httpBindings as $binding) {
                    if (!\is_array($binding)) {
                        continue 2;
                    }

                    $bindingMethod = $binding['method'] ?? null;
                    $bindingPath = $binding['path'] ?? null;

                    if (!\is_string($bindingMethod) || !\is_string($bindingPath)) {
                        continue 2;
                    }

                    $normalizedBindings[] = [
                        'method' => $bindingMethod,
                        'path' => $bindingPath,
                    ];
                }

                /** @var class-string $requestClass */
                /** @var class-string $responseClass */
                /** @var class-string $handler */
                /** @var class-string $endpointInterface */
                /** @var class-string $endpointImplementation */
                $operations[] = [
                    'service' => $service,
                    'method' => $method,
                    'operation_id' => $operationId,
                    'request_class' => $requestClass,
                    'response_class' => $responseClass,
                    'handler' => $handler,
                    'endpoint_interface' => $endpointInterface,
                    'endpoint_implementation' => $endpointImplementation,
                    'http_bindings' => $normalizedBindings,
                ];
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

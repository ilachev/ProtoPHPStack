<?php

declare(strict_types=1);

namespace ProtoPhpGen\Generator;

use ProtoPhpGen\Descriptor\ProtoFileDescriptor;
use ProtoPhpGen\Plugin\PluginOptions;
use ProtoPhpGen\Profile\EndpointProfile;
use ProtoPhpGen\Type\TypeResolver;

final readonly class OperationManifestGenerator implements CodeGeneratorModule
{
    public const MODULE_NAME = 'operation_manifest';

    public function __construct(
        private PluginOptions $options,
        private EndpointProfile $endpointProfile,
    ) {}

    public function getName(): string
    {
        return self::MODULE_NAME;
    }

    public function isEnabled(PluginOptions $options): bool
    {
        return $options->isModuleEnabled(self::MODULE_NAME);
    }

    /**
     * @return list<GeneratedFile>
     */
    public function generateForProtoFile(ProtoFileDescriptor $protoFile, TypeResolver $typeResolver): array
    {
        $operations = [];
        $fileNamespace = $typeResolver->resolveFileNamespace($protoFile);
        if ($fileNamespace === '') {
            return [];
        }

        foreach ($protoFile->getServices() as $service) {
            $serviceName = $service->getName();
            if ($serviceName === '') {
                continue;
            }

            $serviceNamespace = $this->endpointProfile->buildServiceNamespace(
                $this->options->getNamespace(),
                $fileNamespace,
                $serviceName,
            );

            foreach ($service->getMethods() as $method) {
                $methodName = $method->getName();
                $inputType = $method->getInputType();
                $outputType = $method->getOutputType();
                if ($methodName === '' || $inputType === '' || $outputType === '') {
                    continue;
                }

                $inputClass = $typeResolver->resolveTypeClass($inputType);
                $outputClass = $typeResolver->resolveTypeClass($outputType);
                if ($inputClass === null || $outputClass === null) {
                    continue;
                }

                /** @var class-string $endpointInterface */
                $endpointInterface = $serviceNamespace . '\\' . $methodName . 'Endpoint';
                /** @var class-string $handlerClass */
                $handlerClass = $serviceNamespace . '\\' . $methodName . 'HttpHandler';
                /** @var class-string $endpointImplementation */
                $endpointImplementation = $this->endpointProfile->buildEndpointImplementationClass(
                    $fileNamespace,
                    $serviceName,
                    $methodName,
                );

                $httpBindings = [];
                foreach ($method->getHttpBindings() as $binding) {
                    if ($binding->getMethod() === '' || $binding->getPath() === '') {
                        continue;
                    }

                    $httpBindings[] = [
                        'method' => $binding->getMethod(),
                        'path' => $binding->getPath(),
                    ];
                }

                $operations[] = [
                    'service' => $serviceName,
                    'method' => $methodName,
                    'operation_id' => $serviceName . '.' . $methodName,
                    'request_class' => $inputClass,
                    'response_class' => $outputClass,
                    'handler' => $handlerClass,
                    'endpoint_interface' => $endpointInterface,
                    'endpoint_implementation' => $endpointImplementation,
                    'http_bindings' => $httpBindings,
                ];
            }
        }

        if ($operations === []) {
            return [];
        }

        return [new GeneratedFile($this->buildManifestPath($protoFile), $this->renderManifest($operations))];
    }

    private function buildManifestPath(ProtoFileDescriptor $protoFile): string
    {
        $sourceName = $protoFile->getName();
        if ($sourceName === '') {
            throw new \RuntimeException('Operation manifest generation requires the source proto file name');
        }

        $normalizedSourceName = preg_replace('/\.proto$/', '.php', $sourceName);
        if (!\is_string($normalizedSourceName)) {
            throw new \RuntimeException("Unable to normalize proto file name: {$sourceName}");
        }

        return rtrim($this->options->getOutputDir(), '/')
            . '/Generated/OperationManifest/'
            . $normalizedSourceName;
    }

    /**
     * @param list<array{
     *     service: string,
     *     method: string,
     *     operation_id: string,
     *     request_class: class-string,
     *     response_class: class-string,
     *     handler: class-string,
     *     endpoint_interface: class-string,
     *     endpoint_implementation: class-string,
     *     http_bindings: list<array{method: string, path: string}>
     * }> $operations
     */
    private function renderManifest(array $operations): string
    {
        $operationDefinitionClass = $this->endpointProfile->getOperationDefinitionClass();
        $httpOperationBindingClass = $this->endpointProfile->getHttpOperationBindingClass();
        $operationsCode = implode(
            ",\n",
            array_map(
                fn(array $operation): string => $this->renderOperationDefinition($operation),
                $operations,
            ),
        );

        return <<<PHP
            <?php

            declare(strict_types=1);

            use {$httpOperationBindingClass};
            use {$operationDefinitionClass};

            /**
             * WARNING: This file is automatically generated
             * from protobuf definitions. Do not edit manually.
             *
             * @return list<OperationDefinition>
             */
            return [
            {$operationsCode}
            ];

            PHP;
    }

    /**
     * @param array{
     *     service: string,
     *     method: string,
     *     operation_id: string,
     *     request_class: class-string,
     *     response_class: class-string,
     *     handler: class-string,
     *     endpoint_interface: class-string,
     *     endpoint_implementation: class-string,
     *     http_bindings: list<array{method: string, path: string}>
     * } $operation
     */
    private function renderOperationDefinition(
        array $operation,
    ): string {
        $bindings = implode(
            ",\n",
            array_map(
                fn(array $binding): string => $this->renderHttpBinding($binding),
                $operation['http_bindings'],
            ),
        );

        return <<<PHP
                new OperationDefinition(
                    service: {$this->exportString($operation['service'])},
                    method: {$this->exportString($operation['method'])},
                    operationId: {$this->exportString($operation['operation_id'])},
                    requestClass: {$this->exportString($operation['request_class'])},
                    responseClass: {$this->exportString($operation['response_class'])},
                    handler: {$this->exportString($operation['handler'])},
                    endpointInterface: {$this->exportString($operation['endpoint_interface'])},
                    endpointImplementation: {$this->exportString($operation['endpoint_implementation'])},
                    httpBindings: [
            {$bindings}
                    ],
                )
            PHP;
    }

    /**
     * @param array{method: string, path: string} $binding
     */
    private function renderHttpBinding(array $binding): string
    {
        return <<<PHP
                        new HttpOperationBinding(
                            method: {$this->exportString($binding['method'])},
                            path: {$this->exportString($binding['path'])},
                        )
            PHP;
    }

    private function exportString(string $value): string
    {
        return var_export($value, true);
    }
}

<?php

declare(strict_types=1);

namespace ProtoPhpGen\Generator;

use ProtoPhpGen\Descriptor\ProtoFileDescriptor;
use ProtoPhpGen\Plugin\PluginOptions;
use ProtoPhpGen\Profile\TransportProfile;
use ProtoPhpGen\Type\TypeResolver;

final readonly class OperationManifestGenerator implements CodeGeneratorModule
{
    public const MODULE_NAME = 'operation_manifest';

    public function __construct(
        private PluginOptions $options,
        private TransportProfile $transportProfile,
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

            $serviceNamespace = $this->transportProfile->buildServiceNamespace(
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
                $endpointImplementation = $this->transportProfile->buildEndpointImplementationClass(
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

        return [
            new GeneratedFile(
                $this->buildManifestPath($protoFile),
                $this->renderManifest($operations),
            ),
        ];
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
        $operationsCode = var_export($operations, true);

        return <<<PHP
            <?php

            declare(strict_types=1);

            /**
             * WARNING: This file is automatically generated
             * from protobuf definitions. Do not edit manually.
             *
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
            return {$operationsCode};

            PHP;
    }
}

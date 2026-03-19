<?php

declare(strict_types=1);

namespace ProtoPhpGen\Generator;

use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use ProtoPhpGen\Descriptor\ProtoFileDescriptor;
use ProtoPhpGen\Plugin\PluginOptions;
use ProtoPhpGen\Profile\EndpointProfile;
use ProtoPhpGen\Type\TypeResolver;

final readonly class OperationManifestGenerator implements CodeGeneratorModule
{
    public const MODULE_NAME = 'operation_manifest';

    private PsrPrinter $printer;

    public function __construct(
        private PluginOptions $options,
        private EndpointProfile $endpointProfile,
    ) {
        $this->printer = new PsrPrinter();
    }

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

        $registryNamespace = $this->endpointProfile->buildOperationRegistryNamespace(
            $this->options->getNamespace(),
            $fileNamespace,
        );
        $registryClassName = $this->endpointProfile->buildOperationRegistryClassName($protoFile->getName());

        return [
            new GeneratedFile(
                $this->buildRegistryPath($registryNamespace, $registryClassName),
                $this->renderRegistry($registryNamespace, $registryClassName, $operations, $protoFile->getName()),
            ),
        ];
    }

    private function buildRegistryPath(string $registryNamespace, string $registryClassName): string
    {
        $relativeNamespace = str_starts_with($registryNamespace, 'App\\')
            ? substr($registryNamespace, 4)
            : $registryNamespace;

        return rtrim($this->options->getOutputDir(), '/')
            . '/'
            . str_replace('\\', '/', $relativeNamespace)
            . '/'
            . $registryClassName
            . '.php';
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
    private function renderRegistry(
        string $registryNamespace,
        string $registryClassName,
        array $operations,
        string $sourceName,
    ): string {
        $operationDefinitionClass = $this->endpointProfile->getOperationDefinitionClass();
        $httpOperationBindingClass = $this->endpointProfile->getHttpOperationBindingClass();
        $operationRegistryInterface = $this->endpointProfile->getOperationRegistryInterface();

        $file = new PhpFile();
        $file->setStrictTypes();

        $namespace = $file->addNamespace($registryNamespace);
        $namespace->addUse($httpOperationBindingClass);
        $namespace->addUse($operationDefinitionClass);
        $namespace->addUse($operationRegistryInterface);

        $class = $namespace->addClass($registryClassName);
        $class->setFinal(true);
        $class->setReadOnly(true);
        $class->addImplement($operationRegistryInterface);

        $method = $class->addMethod('getOperations');
        $method->setReturnType('array');
        $method->addComment('@return list<OperationDefinition>');
        $method->setBody("return [\n" . $this->renderOperationList($operations) . "\n];");

        return $this->printGeneratedFile($file, $sourceName);
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
    private function renderOperationList(array $operations): string
    {
        return implode(
            ",\n",
            array_map(
                fn(array $operation): string => $this->indent($this->renderOperationDefinition($operation), 2),
                $operations,
            ),
        );
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
                fn(array $binding): string => $this->indent($this->renderHttpBinding($binding), 3),
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

    private function indent(string $content, int $level): string
    {
        $indent = str_repeat('    ', $level);

        return preg_replace('/^/m', $indent, $content) ?? $content;
    }

    private function printGeneratedFile(PhpFile $file, string $sourceName): string
    {
        $content = $this->printer->printFile($file);

        if (!str_starts_with($content, '<?php')) {
            return $content;
        }

        return $this->renderGeneratedHeader($sourceName) . ltrim(substr($content, \strlen('<?php')));
    }

    private function renderGeneratedHeader(string $sourceName): string
    {
        return <<<PHP
            <?php

            /**
             * Generated by the protocol buffer compiler with protoc-php-gen.  DO NOT EDIT!
             * source: {$sourceName}
             */

            PHP;
    }
}

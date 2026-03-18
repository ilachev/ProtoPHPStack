<?php

declare(strict_types=1);

namespace ProtoPhpGen\Generator;

use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use ProtoPhpGen\Config\GeneratorConfig;

final readonly class TransportContractGenerator
{
    private PsrPrinter $printer;

    public function __construct(
        private GeneratorConfig $config,
    ) {
        $this->printer = new PsrPrinter();
    }

    /**
     * @param array<string, mixed> $protoFile
     * @param array<string, class-string> $typeMap
     * @return list<GeneratedFile>
     */
    public function generateForProtoFile(array $protoFile, array $typeMap): array
    {
        if (!$this->config->shouldGenerateTransportContracts()) {
            return [];
        }

        $services = $protoFile['service'] ?? [];
        if (!\is_array($services) || $services === []) {
            return [];
        }

        $fileNamespace = $this->resolveFileNamespace($protoFile);
        if ($fileNamespace === '') {
            return [];
        }

        $files = [];

        foreach ($services as $service) {
            if (!\is_array($service)) {
                continue;
            }

            $serviceName = isset($service['name']) && \is_string($service['name']) ? $service['name'] : '';
            if ($serviceName === '') {
                continue;
            }

            $methods = $service['method'] ?? [];
            if (!\is_array($methods) || $methods === []) {
                continue;
            }

            foreach ($methods as $method) {
                if (!\is_array($method)) {
                    continue;
                }

                $methodName = isset($method['name']) && \is_string($method['name']) ? $method['name'] : '';
                $inputType = isset($method['input_type']) && \is_string($method['input_type']) ? $method['input_type'] : '';
                $outputType = isset($method['output_type']) && \is_string($method['output_type']) ? $method['output_type'] : '';

                if ($methodName === '' || $inputType === '' || $outputType === '') {
                    continue;
                }

                $inputClass = $this->resolveTypeClass($inputType, $typeMap, $fileNamespace);
                $outputClass = $this->resolveTypeClass($outputType, $typeMap, $fileNamespace);
                if ($inputClass === null || $outputClass === null) {
                    continue;
                }

                $serviceNamespace = $this->buildServiceNamespace($fileNamespace, $serviceName);
                $files[] = $this->generateEndpointInterface(
                    $serviceNamespace,
                    $methodName,
                    $inputClass,
                    $outputClass,
                );
                $files[] = $this->generateHttpHandler(
                    $serviceNamespace,
                    $methodName,
                    $inputClass,
                    $outputClass,
                );
            }
        }

        return $files;
    }

    private function resolveFileNamespace(array $protoFile): string
    {
        $options = $protoFile['options'] ?? [];
        if (\is_array($options)) {
            $phpNamespace = $options['php_namespace'] ?? null;
            if (\is_string($phpNamespace) && $phpNamespace !== '') {
                return $phpNamespace;
            }
        }

        $package = $protoFile['package'] ?? null;
        if (!\is_string($package) || $package === '') {
            return '';
        }

        $parts = array_map('ucfirst', explode('.', $package));

        return 'App\\' . implode('\\', $parts);
    }

    /**
     * @param array<string, class-string> $typeMap
     * @return class-string|null
     */
    private function resolveTypeClass(string $typeName, array $typeMap, string $fileNamespace): ?string
    {
        if (isset($typeMap[$typeName])) {
            return $typeMap[$typeName];
        }

        $trimmedType = ltrim($typeName, '.');
        if ($trimmedType === '') {
            return null;
        }

        $shortName = substr($trimmedType, (int) strrpos('.' . $trimmedType, '.'));
        $shortName = ltrim($shortName, '.');
        if ($shortName === '') {
            return null;
        }

        return "{$fileNamespace}\\{$shortName}";
    }

    private function buildServiceNamespace(string $fileNamespace, string $serviceName): string
    {
        $suffix = str_starts_with($fileNamespace, 'App\\')
            ? substr($fileNamespace, 4)
            : $fileNamespace;

        return rtrim($this->config->getNamespace(), '\\') . '\\' . $suffix . '\\' . $serviceName;
    }

    private function generateEndpointInterface(
        string $serviceNamespace,
        string $methodName,
        string $inputClass,
        string $outputClass,
    ): GeneratedFile {
        $file = new PhpFile();
        $file->setStrictTypes();

        $namespace = $file->addNamespace($serviceNamespace);
        $namespace->addUse($inputClass);
        $namespace->addUse($outputClass);
        $namespace->addUse('Psr\Http\Message\ServerRequestInterface');

        $interface = $namespace->addInterface($methodName . 'Endpoint');
        $handle = $interface->addMethod('handle');
        $handle->addParameter('request')->setType($inputClass);
        $handle->addParameter('httpRequest')->setType('Psr\Http\Message\ServerRequestInterface');
        $handle->setReturnType($outputClass);

        return new GeneratedFile(
            $this->buildFilePath($serviceNamespace, $methodName . 'Endpoint'),
            $this->printer->printFile($file),
        );
    }

    private function generateHttpHandler(
        string $serviceNamespace,
        string $methodName,
        string $inputClass,
        string $outputClass,
    ): GeneratedFile {
        $file = new PhpFile();
        $file->setStrictTypes();

        $namespace = $file->addNamespace($serviceNamespace);
        $namespace->addUse($inputClass);
        $namespace->addUse($outputClass);
        $namespace->addUse('App\Platform\Http\Handler\AbstractProtobufRpcHandler');
        $namespace->addUse('App\Platform\Http\JsonResponse');
        $namespace->addUse('Psr\Http\Message\ResponseInterface');
        $namespace->addUse('Psr\Http\Message\ServerRequestInterface');

        $class = $namespace->addClass($methodName . 'HttpHandler');
        $class->setFinal(true);
        $class->setReadOnly(true);
        $class->setExtends('App\Platform\Http\Handler\AbstractProtobufRpcHandler');

        $constructor = $class->addMethod('__construct');
        $constructor->addPromotedParameter('endpoint')
            ->setPrivate()
            ->setType($serviceNamespace . '\\' . $methodName . 'Endpoint');
        $constructor->addParameter('jsonResponse')->setType('App\Platform\Http\JsonResponse');
        $constructor->setBody('parent::__construct($jsonResponse);');

        $handle = $class->addMethod('handle');
        $handle->addComment('@throws \JsonException');
        $handle->addParameter('request')->setType('Psr\Http\Message\ServerRequestInterface');
        $handle->setReturnType('Psr\Http\Message\ResponseInterface');
        $inputShortName = $this->shortName($inputClass);
        $outputShortName = $this->shortName($outputClass);
        $handle->setBody(
            '$message = $this->decodeRequest($request, ' . $inputShortName . "::class);\n"
            . 'if (!$message instanceof ' . $inputShortName . ") {\n"
            . "    return \$this->invalidRequestResponse();\n"
            . "}\n\n"
            . '/** @var ' . $outputShortName . " \$response */\n"
            . '$response = $this->endpoint->handle($message, $request);' . "\n\n"
            . 'return $this->protobufResponse($response);',
        );

        return new GeneratedFile(
            $this->buildFilePath($serviceNamespace, $methodName . 'HttpHandler'),
            $this->printer->printFile($file),
        );
    }

    private function buildFilePath(string $namespace, string $className): string
    {
        $relativeNamespace = str_starts_with($namespace, 'App\\')
            ? substr($namespace, 4)
            : $namespace;

        return rtrim($this->config->getOutputDir(), '/')
            . '/'
            . str_replace('\\', '/', $relativeNamespace)
            . '/'
            . $className
            . '.php';
    }

    private function shortName(string $className): string
    {
        $position = strrpos($className, '\\');

        return $position === false ? $className : substr($className, $position + 1);
    }
}

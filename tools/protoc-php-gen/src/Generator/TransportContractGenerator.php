<?php

declare(strict_types=1);

namespace ProtoPhpGen\Generator;

use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use ProtoPhpGen\Descriptor\ProtoFileDescriptor;
use ProtoPhpGen\Plugin\PluginOptions;

final readonly class TransportContractGenerator implements CodeGeneratorModule
{
    public const MODULE_NAME = 'transport_contracts';

    private PsrPrinter $printer;

    public function __construct(
        private PluginOptions $options,
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
     * @param array<string, class-string> $typeMap
     * @return list<GeneratedFile>
     */
    public function generateForProtoFile(ProtoFileDescriptor $protoFile, array $typeMap): array
    {
        $services = $protoFile->getServices();
        if ($services === []) {
            return [];
        }

        $fileNamespace = $this->resolveFileNamespace($protoFile);
        if ($fileNamespace === '') {
            return [];
        }

        $files = [];

        foreach ($services as $service) {
            $serviceName = $service->getName();
            if ($serviceName === '') {
                continue;
            }

            $methods = $service->getMethods();
            if ($methods === []) {
                continue;
            }

            foreach ($methods as $method) {
                $methodName = $method->getName();
                $inputType = $method->getInputType();
                $outputType = $method->getOutputType();

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

    private function resolveFileNamespace(ProtoFileDescriptor $protoFile): string
    {
        $options = $protoFile->getOptions();
        $phpNamespace = $options?->getPhpNamespace();
        if ($phpNamespace !== null && $phpNamespace !== '') {
            return $phpNamespace;
        }

        $package = $protoFile->getPackage();
        if ($package === '') {
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

        $resolvedClass = "{$fileNamespace}\\{$shortName}";

        /** @var class-string $resolvedClass */
        return $resolvedClass;
    }

    private function buildServiceNamespace(string $fileNamespace, string $serviceName): string
    {
        $suffix = str_starts_with($fileNamespace, 'App\\')
            ? substr($fileNamespace, 4)
            : $fileNamespace;

        return rtrim($this->options->getNamespace(), '\\') . '\\' . $suffix . '\\' . $serviceName;
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

        return rtrim($this->options->getOutputDir(), '/')
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

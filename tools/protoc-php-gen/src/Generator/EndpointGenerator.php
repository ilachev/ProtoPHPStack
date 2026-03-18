<?php

declare(strict_types=1);

namespace ProtoPhpGen\Generator;

use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use ProtoPhpGen\Descriptor\ProtoFileDescriptor;
use ProtoPhpGen\Plugin\PluginOptions;
use ProtoPhpGen\Profile\EndpointProfile;
use ProtoPhpGen\Type\TypeResolver;

final readonly class EndpointGenerator implements CodeGeneratorModule
{
    public const MODULE_NAME = 'endpoints';

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
        $services = $protoFile->getServices();
        if ($services === []) {
            return [];
        }

        $fileNamespace = $typeResolver->resolveFileNamespace($protoFile);
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

                $inputClass = $typeResolver->resolveTypeClass($inputType);
                $outputClass = $typeResolver->resolveTypeClass($outputType);
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

    private function buildServiceNamespace(string $fileNamespace, string $serviceName): string
    {
        return $this->endpointProfile->buildServiceNamespace(
            $this->options->getNamespace(),
            $fileNamespace,
            $serviceName,
        );
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

        $handlerBaseClass = $this->endpointProfile->getHandlerBaseClass();
        $responseHelperClass = $this->endpointProfile->getResponseHelperClass();
        $responseHelperParameterName = $this->endpointProfile->getResponseHelperParameterName();
        $namespace = $file->addNamespace($serviceNamespace);
        $namespace->addUse($inputClass);
        $namespace->addUse($outputClass);
        $namespace->addUse($handlerBaseClass);
        $namespace->addUse($responseHelperClass);
        $namespace->addUse('Psr\Http\Message\ResponseInterface');
        $namespace->addUse('Psr\Http\Message\ServerRequestInterface');

        $class = $namespace->addClass($methodName . 'HttpHandler');
        $class->setFinal(true);
        $class->setReadOnly(true);
        $class->setExtends($handlerBaseClass);

        $constructor = $class->addMethod('__construct');
        $constructor->addPromotedParameter('endpoint')
            ->setPrivate()
            ->setType($serviceNamespace . '\\' . $methodName . 'Endpoint');
        $constructor->addParameter($responseHelperParameterName)->setType($responseHelperClass);
        $constructor->setBody('parent::__construct($' . $responseHelperParameterName . ');');

        $handle = $class->addMethod('handle');
        $handle->addComment('@throws \JsonException');
        $handle->addParameter('request')->setType('Psr\Http\Message\ServerRequestInterface');
        $handle->setReturnType('Psr\Http\Message\ResponseInterface');
        $inputShortName = $this->shortName($inputClass);
        $outputShortName = $this->shortName($outputClass);
        $handle->setBody(
            '$message = $this->' . $this->endpointProfile->getDecodeRequestMethodName() . '($request, ' . $inputShortName . "::class);\n"
            . 'if (!$message instanceof ' . $inputShortName . ") {\n"
            . '    return $this->' . $this->endpointProfile->getInvalidRequestResponseMethodName() . "();\n"
            . "}\n\n"
            . '/** @var ' . $outputShortName . " \$response */\n"
            . '$response = $this->endpoint->handle($message, $request);' . "\n\n"
            . 'return $this->' . $this->endpointProfile->getSuccessResponseMethodName() . '($response);',
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

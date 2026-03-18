<?php

declare(strict_types=1);

namespace ProtoPhpGen\Generator;

use ProtoPhpGen\Descriptor\ProtoFileDescriptor;
use ProtoPhpGen\Plugin\PluginOptions;
use ProtoPhpGen\Profile\TransportProfile;
use ProtoPhpGen\Type\TypeResolver;

final readonly class EndpointImplementationValidator implements CodeGeneratorModule
{
    public const MODULE_NAME = 'endpoint_validation';

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
        $fileNamespace = $typeResolver->resolveFileNamespace($protoFile);
        if ($fileNamespace === '') {
            return [];
        }

        foreach ($protoFile->getServices() as $service) {
            $serviceName = $service->getName();
            if ($serviceName === '') {
                continue;
            }

            foreach ($service->getMethods() as $method) {
                $methodName = $method->getName();
                if ($methodName === '') {
                    continue;
                }

                $expectedClass = $this->transportProfile->buildEndpointImplementationClass(
                    $fileNamespace,
                    $serviceName,
                    $methodName,
                );
                $expectedPath = $this->transportProfile->buildEndpointImplementationPath(
                    $this->options->getSourceRoot(),
                    $fileNamespace,
                    $serviceName,
                    $methodName,
                );

                if (!is_file($expectedPath)) {
                    throw new \RuntimeException(
                        "Missing handwritten endpoint implementation {$expectedClass} at {$expectedPath}",
                    );
                }

                $declaredClass = $this->extractDeclaredClass($expectedPath);
                if ($declaredClass !== $expectedClass) {
                    throw new \RuntimeException(
                        "Endpoint implementation file {$expectedPath} must declare {$expectedClass}",
                    );
                }
            }
        }

        return [];
    }

    private function extractDeclaredClass(string $path): ?string
    {
        $contents = file_get_contents($path);
        if (!\is_string($contents)) {
            return null;
        }

        $tokens = token_get_all($contents);
        $namespace = '';
        $className = null;
        $tokenCount = \count($tokens);

        for ($index = 0; $index < $tokenCount; ++$index) {
            $token = $tokens[$index];
            if (!\is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = $this->collectQualifiedName($tokens, $index + 1);

                continue;
            }

            if ($token[0] === T_CLASS) {
                $className = $this->collectClassName($tokens, $index + 1);
                break;
            }
        }

        if ($className === null || $className === '') {
            return null;
        }

        return $namespace === '' ? $className : $namespace . '\\' . $className;
    }

    /**
     * @param list<mixed> $tokens
     */
    private function collectQualifiedName(array $tokens, int $startIndex): string
    {
        $parts = [];
        $tokenCount = \count($tokens);

        for ($index = $startIndex; $index < $tokenCount; ++$index) {
            $token = $tokens[$index];
            if (!\is_array($token)) {
                if ($token === ';' || $token === '{') {
                    break;
                }

                continue;
            }

            if ($token[0] === T_STRING || $token[0] === T_NAME_QUALIFIED) {
                $parts[] = $token[1];

                continue;
            }

            if ($token[0] === T_NS_SEPARATOR) {
                continue;
            }
        }

        return implode('\\', $parts);
    }

    /**
     * @param list<mixed> $tokens
     */
    private function collectClassName(array $tokens, int $startIndex): ?string
    {
        $tokenCount = \count($tokens);

        for ($index = $startIndex; $index < $tokenCount; ++$index) {
            $token = $tokens[$index];
            if (!\is_array($token)) {
                continue;
            }

            if ($token[0] === T_STRING) {
                return $token[1];
            }
        }

        return null;
    }
}

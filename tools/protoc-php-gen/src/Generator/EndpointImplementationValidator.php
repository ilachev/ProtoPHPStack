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

                $expectedInterface = $this->transportProfile->buildServiceNamespace(
                    $this->options->getNamespace(),
                    $fileNamespace,
                    $serviceName,
                ) . '\\' . $methodName . 'Endpoint';

                if (!$this->declaresImplementedInterface($expectedPath, $expectedInterface)) {
                    throw new \RuntimeException(
                        "Endpoint implementation {$expectedClass} must implement {$expectedInterface}",
                    );
                }
            }
        }

        return [];
    }

    private function extractDeclaredClass(string $path): ?string
    {
        $definition = $this->extractClassDefinition($path);

        return $definition['class'];
    }

    private function declaresImplementedInterface(string $path, string $expectedInterface): bool
    {
        $definition = $this->extractClassDefinition($path);

        return \in_array($expectedInterface, $definition['interfaces'], true);
    }

    /**
     * @return array{
     *     class: class-string|null,
     *     interfaces: list<class-string>
     * }
     */
    private function extractClassDefinition(string $path): array
    {
        $contents = file_get_contents($path);
        if (!\is_string($contents)) {
            return [
                'class' => null,
                'interfaces' => [],
            ];
        }

        $tokens = token_get_all($contents);
        $namespace = '';
        $uses = [];
        $className = null;
        $interfaces = [];
        $tokenCount = \count($tokens);
        $braceLevel = 0;

        for ($index = 0; $index < $tokenCount; ++$index) {
            $token = $tokens[$index];

            if (!\is_array($token)) {
                if ($token === '{') {
                    ++$braceLevel;
                } elseif ($token === '}') {
                    --$braceLevel;
                }

                continue;
            }

            if ($braceLevel !== 0) {
                continue;
            }

            if ($token[0] === T_USE) {
                $uses += $this->collectUseStatements($tokens, $index + 1);

                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = $this->collectQualifiedName($tokens, $index + 1);

                continue;
            }

            if ($token[0] === T_CLASS) {
                $className = $this->collectClassName($tokens, $index + 1);
                $interfaces = $this->collectImplementedInterfaces($tokens, $index + 1, $namespace, $uses);
                break;
            }
        }

        if ($className === null || $className === '') {
            return [
                'class' => null,
                'interfaces' => [],
            ];
        }

        /** @var class-string $resolvedClassName */
        $resolvedClassName = $namespace === '' ? $className : $namespace . '\\' . $className;

        return [
            'class' => $resolvedClassName,
            'interfaces' => $interfaces,
        ];
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

    /**
     * @param list<mixed> $tokens
     * @return array<string, string>
     */
    private function collectUseStatements(array $tokens, int $startIndex): array
    {
        $imports = [];
        $tokenCount = \count($tokens);
        $currentName = '';
        $currentAlias = null;
        $parsingAlias = false;

        for ($index = $startIndex; $index < $tokenCount; ++$index) {
            $token = $tokens[$index];

            if (!\is_array($token)) {
                if ($token === ',') {
                    if ($currentName !== '') {
                        $imports[$currentAlias ?? $this->shortName($currentName)] = ltrim($currentName, '\\');
                    }

                    $currentName = '';
                    $currentAlias = null;
                    $parsingAlias = false;

                    continue;
                }

                if ($token === ';') {
                    break;
                }

                continue;
            }

            if ($token[0] === T_AS) {
                $parsingAlias = true;

                continue;
            }

            if ($token[0] === T_STRING || $token[0] === T_NAME_QUALIFIED || $token[0] === T_NAME_FULLY_QUALIFIED) {
                if ($parsingAlias) {
                    $currentAlias = $token[1];

                    continue;
                }

                $currentName = $token[1];
            }
        }

        if ($currentName !== '') {
            $imports[$currentAlias ?? $this->shortName($currentName)] = ltrim($currentName, '\\');
        }

        return $imports;
    }

    /**
     * @param list<mixed> $tokens
     * @param array<string, string> $uses
     * @return list<class-string>
     */
    private function collectImplementedInterfaces(
        array $tokens,
        int $startIndex,
        string $namespace,
        array $uses,
    ): array {
        $tokenCount = \count($tokens);
        $interfaces = [];
        $collecting = false;
        $currentName = '';

        for ($index = $startIndex; $index < $tokenCount; ++$index) {
            $token = $tokens[$index];

            if (!\is_array($token)) {
                if ($token === '{') {
                    break;
                }

                if ($collecting && $token === ',') {
                    if ($currentName !== '') {
                        $interfaces[] = $this->resolveImportedName($currentName, $namespace, $uses);
                    }

                    $currentName = '';

                    continue;
                }

                continue;
            }

            if ($token[0] === T_IMPLEMENTS) {
                $collecting = true;

                continue;
            }

            if (!$collecting) {
                continue;
            }

            if (
                $token[0] === T_STRING
                || $token[0] === T_NAME_QUALIFIED
                || $token[0] === T_NAME_FULLY_QUALIFIED
                || $token[0] === T_NS_SEPARATOR
            ) {
                $currentName .= $token[1];
            }
        }

        if ($collecting && $currentName !== '') {
            $interfaces[] = $this->resolveImportedName($currentName, $namespace, $uses);
        }

        /** @var list<class-string> $interfaces */
        return $interfaces;
    }

    /**
     * @param array<string, string> $uses
     * @return class-string
     */
    private function resolveImportedName(string $name, string $namespace, array $uses): string
    {
        if (str_starts_with($name, '\\')) {
            /** @var class-string $resolvedName */
            $resolvedName = ltrim($name, '\\');

            return $resolvedName;
        }

        $firstSeparator = strpos($name, '\\');
        $firstSegment = $firstSeparator === false ? $name : substr($name, 0, $firstSeparator);
        if (isset($uses[$firstSegment])) {
            $suffix = $firstSeparator === false ? '' : substr($name, $firstSeparator);

            /** @var class-string $resolvedName */
            $resolvedName = $uses[$firstSegment] . $suffix;

            return $resolvedName;
        }

        /** @var class-string $resolvedName */
        $resolvedName = $namespace === '' ? $name : $namespace . '\\' . $name;

        return $resolvedName;
    }

    private function shortName(string $className): string
    {
        $position = strrpos($className, '\\');

        return $position === false ? $className : substr($className, $position + 1);
    }
}

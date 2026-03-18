<?php

declare(strict_types=1);

namespace ProtoPhpGen\Type;

use ProtoPhpGen\Descriptor\ProtoFileDescriptor;

final readonly class TypeResolver
{
    /**
     * @param array<string, class-string> $typeMap
     */
    public function __construct(
        private PhpNamespaceResolver $namespaceResolver,
        private array $typeMap,
    ) {}

    /**
     * @param array<string, ProtoFileDescriptor> $protoFiles
     */
    public static function fromProtoFiles(
        array $protoFiles,
        ?PhpNamespaceResolver $namespaceResolver = null,
    ): self {
        $namespaceResolver ??= new PhpNamespaceResolver();
        $typeMap = [];

        foreach ($protoFiles as $protoFile) {
            $namespace = $namespaceResolver->resolveFileNamespace($protoFile);
            if ($namespace === '') {
                continue;
            }

            $package = $protoFile->getPackage();
            if ($package === '') {
                continue;
            }

            $namespaceResolver->addMessagesToTypeMap(
                $typeMap,
                $protoFile->getMessages(),
                $namespace,
                $package,
            );
        }

        return new self($namespaceResolver, $typeMap);
    }

    public function resolveFileNamespace(ProtoFileDescriptor $protoFile): string
    {
        return $this->namespaceResolver->resolveFileNamespace($protoFile);
    }

    /**
     * @return class-string|null
     */
    public function resolveTypeClass(string $protobufTypeName): ?string
    {
        return $this->typeMap[$protobufTypeName] ?? null;
    }

    /**
     * @return array<string, class-string>
     */
    public function getTypeMap(): array
    {
        return $this->typeMap;
    }
}

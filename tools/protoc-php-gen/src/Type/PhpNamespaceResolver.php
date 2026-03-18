<?php

declare(strict_types=1);

namespace ProtoPhpGen\Type;

use ProtoPhpGen\Descriptor\MessageDescriptor;
use ProtoPhpGen\Descriptor\ProtoFileDescriptor;

final readonly class PhpNamespaceResolver
{
    public function resolveFileNamespace(ProtoFileDescriptor $protoFile): string
    {
        $phpNamespace = $protoFile->getOptions()?->getPhpNamespace();
        if ($phpNamespace !== null && $phpNamespace !== '') {
            return $phpNamespace;
        }

        $package = $protoFile->getPackage();
        if ($package === '') {
            return '';
        }

        return $this->packageToNamespace($package);
    }

    /**
     * @param array<string, class-string> $typeMap
     * @param list<MessageDescriptor> $messages
     */
    public function addMessagesToTypeMap(
        array &$typeMap,
        array $messages,
        string $namespace,
        string $package,
        string $prefix = '',
    ): void {
        foreach ($messages as $message) {
            $messageName = $message->getName();
            if ($messageName === '') {
                continue;
            }

            $protobufPath = $prefix === '' ? $messageName : $prefix . '.' . $messageName;
            $resolvedClass = $namespace . '\\' . str_replace('.', '\\', $protobufPath);

            /** @var class-string $resolvedClass */
            $typeMap[".{$package}.{$protobufPath}"] = $resolvedClass;

            $this->addMessagesToTypeMap(
                $typeMap,
                $message->getNestedMessages(),
                $namespace,
                $package,
                $protobufPath,
            );
        }
    }

    private function packageToNamespace(string $package): string
    {
        $parts = explode('.', $package);
        $parts = array_map('ucfirst', $parts);

        return 'App\\' . implode('\\', $parts);
    }
}

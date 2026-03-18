<?php

declare(strict_types=1);

namespace ProtoPhpGen;

use ProtoPhpGen\Descriptor\MessageDescriptor;
use ProtoPhpGen\Descriptor\ProtoFileDescriptor;
use ProtoPhpGen\Generator\GeneratorRegistry;
use ProtoPhpGen\Generator\TransportContractGenerator;
use ProtoPhpGen\Plugin\PluginOptions;
use ProtoPhpGen\Protoc\PluginRequest;
use ProtoPhpGen\Protoc\PluginResponse;
use ProtoPhpGen\Protoc\ProtocPlugin;

final readonly class PhpGeneratorPlugin extends ProtocPlugin
{
    public function process(PluginRequest $request): PluginResponse
    {
        $response = new PluginResponse();

        try {
            $this->logDebug('Starting transport contract generation');
            $this->logDebug('Files to generate: ' . implode(', ', $request->getFilesToGenerate()));
            $this->logDebug('Parameters: ' . json_encode($request->getParameters()));

            $options = PluginOptions::fromRequest($request);
            $registry = new GeneratorRegistry(
                [
                    new TransportContractGenerator($options),
                ],
                $options,
            );
            $filesToGenerate = array_flip($request->getFilesToGenerate());
            $protoFiles = $request->getProtoFiles();
            $typeMap = $this->buildTypeMap($protoFiles);

            foreach ($protoFiles as $fileName => $protoFile) {
                if (!isset($filesToGenerate[$fileName])) {
                    continue;
                }

                foreach ($registry->generateForProtoFile($protoFile, $typeMap) as $file) {
                    $response->addFile($file->getName(), $file->getContent());
                    $this->logDebug("Generated file: {$file->getName()}");
                }
            }

            $this->logDebug('Transport contract generation completed successfully');
        } catch (\Throwable $e) {
            $error = "Code generation error: {$e->getMessage()}";
            $response->setError($error);
            $this->logDebug($error);
            $this->logDebug("Stack trace: {$e->getTraceAsString()}");
        }

        return $response;
    }

    /**
     * @param array<string, ProtoFileDescriptor> $protoFiles
     * @return array<string, class-string>
     */
    private function buildTypeMap(array $protoFiles): array
    {
        $typeMap = [];

        foreach ($protoFiles as $protoFile) {
            $namespace = $this->resolveFileNamespace($protoFile);
            if ($namespace === '') {
                continue;
            }

            $package = $protoFile->getPackage();
            if ($package === '') {
                continue;
            }

            $this->addMessagesToTypeMap($typeMap, $protoFile->getMessages(), $namespace, $package);
        }

        return $typeMap;
    }

    private function resolveFileNamespace(ProtoFileDescriptor $protoFile): string
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

    private function packageToNamespace(string $package): string
    {
        $parts = explode('.', $package);
        $parts = array_map('ucfirst', $parts);

        return 'App\\' . implode('\\', $parts);
    }

    /**
     * @param array<string, class-string> $typeMap
     * @param list<MessageDescriptor> $messages
     */
    private function addMessagesToTypeMap(array &$typeMap, array $messages, string $namespace, string $package, string $prefix = ''): void
    {
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

    private function logDebug(string $message): void
    {
        if (getenv('PROTOC_PHP_GEN_DEBUG') === 'true') {
            fwrite(STDERR, "[DEBUG] {$message}\n");
        }
    }
}

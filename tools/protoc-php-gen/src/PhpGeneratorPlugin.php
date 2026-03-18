<?php

declare(strict_types=1);

namespace ProtoPhpGen;

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
                if (!isset($filesToGenerate[$fileName]) || !\is_array($protoFile)) {
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
     * @param array<string, mixed> $protoFiles
     * @return array<string, class-string>
     */
    private function buildTypeMap(array $protoFiles): array
    {
        $typeMap = [];

        foreach ($protoFiles as $protoFile) {
            if (!\is_array($protoFile)) {
                continue;
            }

            $namespace = $this->resolveFileNamespace($protoFile);
            if ($namespace === '') {
                continue;
            }

            $package = $protoFile['package'] ?? null;
            if (!\is_string($package) || $package === '') {
                continue;
            }

            $messageTypes = $protoFile['message_type'] ?? [];
            if (!\is_array($messageTypes)) {
                continue;
            }

            foreach ($messageTypes as $messageType) {
                if (!\is_array($messageType)) {
                    continue;
                }

                $messageName = $messageType['name'] ?? null;
                if (!\is_string($messageName) || $messageName === '') {
                    continue;
                }

                $resolvedClass = "{$namespace}\\{$messageName}";

                /** @var class-string $resolvedClass */
                $typeMap[".{$package}.{$messageName}"] = $resolvedClass;
            }
        }

        return $typeMap;
    }

    /**
     * @param array<string, mixed> $protoFile
     */
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

        return $this->packageToNamespace($package);
    }

    private function packageToNamespace(string $package): string
    {
        $parts = explode('.', $package);
        $parts = array_map('ucfirst', $parts);

        return 'App\\' . implode('\\', $parts);
    }

    private function logDebug(string $message): void
    {
        if (getenv('PROTOC_PHP_GEN_DEBUG') === 'true') {
            fwrite(STDERR, "[DEBUG] {$message}\n");
        }
    }
}

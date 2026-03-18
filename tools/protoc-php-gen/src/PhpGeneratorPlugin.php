<?php

declare(strict_types=1);

namespace ProtoPhpGen;

use ProtoPhpGen\Generator\EndpointImplementationValidator;
use ProtoPhpGen\Generator\GeneratorRegistry;
use ProtoPhpGen\Generator\OperationManifestGenerator;
use ProtoPhpGen\Generator\TransportContractGenerator;
use ProtoPhpGen\Plugin\PluginOptions;
use ProtoPhpGen\Profile\TransportProfileRegistry;
use ProtoPhpGen\Protoc\PluginRequest;
use ProtoPhpGen\Protoc\PluginResponse;
use ProtoPhpGen\Protoc\ProtocPlugin;
use ProtoPhpGen\Type\TypeResolver;

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
            $transportProfile = (new TransportProfileRegistry())->get($options->getTransportProfile());
            $registry = new GeneratorRegistry(
                [
                    new TransportContractGenerator($options, $transportProfile),
                    new EndpointImplementationValidator($options, $transportProfile),
                    new OperationManifestGenerator($options, $transportProfile),
                ],
                $options,
            );
            $filesToGenerate = array_flip($request->getFilesToGenerate());
            $protoFiles = $request->getProtoFiles();
            $typeResolver = TypeResolver::fromProtoFiles($protoFiles);

            foreach ($protoFiles as $fileName => $protoFile) {
                if (!isset($filesToGenerate[$fileName])) {
                    continue;
                }

                foreach ($registry->generateForProtoFile($protoFile, $typeResolver) as $file) {
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

    private function logDebug(string $message): void
    {
        if (getenv('PROTOC_PHP_GEN_DEBUG') === 'true') {
            fwrite(STDERR, "[DEBUG] {$message}\n");
        }
    }
}

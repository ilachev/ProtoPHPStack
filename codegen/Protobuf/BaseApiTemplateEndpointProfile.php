<?php

declare(strict_types=1);

namespace ProjectCodegen\Protobuf;

use ProtoPhpGen\Profile\EndpointProfile;

final readonly class BaseApiTemplateEndpointProfile implements EndpointProfile
{
    public function getName(): string
    {
        return 'base_api_template';
    }

    public function buildServiceNamespace(string $generatedNamespace, string $fileNamespace, string $serviceName): string
    {
        $suffix = str_starts_with($fileNamespace, 'App\\')
            ? substr($fileNamespace, 4)
            : $fileNamespace;

        return rtrim($generatedNamespace, '\\') . '\\' . $suffix . '\\' . $serviceName;
    }

    public function buildEndpointImplementationClass(string $fileNamespace, string $serviceName, string $methodName): string
    {
        $suffix = str_starts_with($fileNamespace, 'App\\')
            ? substr($fileNamespace, 4)
            : $fileNamespace;

        return 'App\Platform\Http\Endpoint\\' . $suffix . '\\' . $serviceName . '\\' . $methodName . 'Endpoint';
    }

    public function buildOperationRegistryNamespace(string $generatedNamespace, string $fileNamespace): string
    {
        $suffix = str_starts_with($fileNamespace, 'App\\')
            ? substr($fileNamespace, 4)
            : $fileNamespace;

        $baseNamespace = str_ends_with($generatedNamespace, '\Endpoint')
            ? substr($generatedNamespace, 0, -\strlen('\Endpoint')) . '\OperationManifest'
            : rtrim($generatedNamespace, '\\') . '\OperationManifest';

        return rtrim($baseNamespace, '\\') . '\\' . $suffix;
    }

    public function buildOperationRegistryClassName(string $sourceName): string
    {
        $basename = pathinfo($sourceName, \PATHINFO_FILENAME);
        $normalized = preg_replace('/[^A-Za-z0-9]+/', ' ', $basename);
        if (!\is_string($normalized) || $normalized === '') {
            throw new \RuntimeException("Unable to build operation registry class name for source: {$sourceName}");
        }

        return str_replace(' ', '', ucwords($normalized)) . 'OperationRegistry';
    }

    public function buildEndpointImplementationPath(
        string $sourceRoot,
        string $fileNamespace,
        string $serviceName,
        string $methodName,
    ): string {
        $suffix = str_starts_with($fileNamespace, 'App\\')
            ? substr($fileNamespace, 4)
            : $fileNamespace;

        return rtrim($sourceRoot, '/')
            . '/Platform/Http/Endpoint/'
            . str_replace('\\', '/', $suffix)
            . '/'
            . $serviceName
            . '/'
            . $methodName
            . 'Endpoint.php';
    }

    public function getHandlerBaseClass(): string
    {
        return 'App\Platform\Http\Handler\AbstractProtobufRpcHandler';
    }

    public function getResponseHelperClass(): string
    {
        return 'App\Platform\Http\JsonResponse';
    }

    public function getOperationDefinitionClass(): string
    {
        return 'App\Platform\Http\Operation\OperationDefinition';
    }

    public function getHttpOperationBindingClass(): string
    {
        return 'App\Platform\Http\Operation\HttpOperationBinding';
    }

    public function getOperationRegistryInterface(): string
    {
        return 'App\Platform\Http\Operation\OperationRegistry';
    }

    public function getResponseHelperParameterName(): string
    {
        return 'jsonResponse';
    }

    public function getDecodeRequestMethodName(): string
    {
        return 'decodeRequest';
    }

    public function getInvalidRequestResponseMethodName(): string
    {
        return 'invalidRequestResponse';
    }

    public function getSuccessResponseMethodName(): string
    {
        return 'protobufResponse';
    }
}

<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use ProtoPhpGen\Profile\EndpointProfile;

final readonly class TestEndpointProfile implements EndpointProfile
{
    public function getName(): string
    {
        return 'test';
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

        return 'App\TestEndpoint\\' . $suffix . '\\' . $serviceName . '\\' . $methodName . 'Endpoint';
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

        return ucfirst($basename) . 'OperationRegistry';
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
            . '/Endpoints/'
            . str_replace('\\', '/', $suffix)
            . '/'
            . $serviceName
            . '/'
            . $methodName
            . 'Endpoint.php';
    }

    public function getHandlerBaseClass(): string
    {
        return 'App\TestRuntime\Handler\BaseHandler';
    }

    public function getResponseHelperClass(): string
    {
        return 'App\TestRuntime\ResponseFactory';
    }

    public function getOperationDefinitionClass(): string
    {
        return 'App\TestRuntime\Operation\OperationDefinition';
    }

    public function getHttpOperationBindingClass(): string
    {
        return 'App\TestRuntime\Operation\HttpOperationBinding';
    }

    public function getOperationRegistryInterface(): string
    {
        return 'App\TestRuntime\Operation\OperationRegistry';
    }

    public function getResponseHelperParameterName(): string
    {
        return 'responseFactory';
    }

    public function getDecodeRequestMethodName(): string
    {
        return 'decode';
    }

    public function getInvalidRequestResponseMethodName(): string
    {
        return 'invalid';
    }

    public function getSuccessResponseMethodName(): string
    {
        return 'success';
    }
}

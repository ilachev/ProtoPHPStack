<?php

declare(strict_types=1);

namespace ProtoPhpGen\Profile;

interface EndpointProfile
{
    public function getName(): string;

    public function buildServiceNamespace(string $generatedNamespace, string $fileNamespace, string $serviceName): string;

    public function buildEndpointImplementationClass(string $fileNamespace, string $serviceName, string $methodName): string;

    public function buildEndpointImplementationPath(
        string $sourceRoot,
        string $fileNamespace,
        string $serviceName,
        string $methodName,
    ): string;

    public function getHandlerBaseClass(): string;

    public function getResponseHelperClass(): string;

    public function getResponseHelperParameterName(): string;

    public function getDecodeRequestMethodName(): string;

    public function getInvalidRequestResponseMethodName(): string;

    public function getSuccessResponseMethodName(): string;
}

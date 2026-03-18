<?php

declare(strict_types=1);

namespace ProtoPhpGen\Profile;

final readonly class BaseApiTemplateTransportProfile implements TransportProfile
{
    public const NAME = 'base_api_template';

    public function getName(): string
    {
        return self::NAME;
    }

    public function buildServiceNamespace(string $generatedNamespace, string $fileNamespace, string $serviceName): string
    {
        $suffix = str_starts_with($fileNamespace, 'App\\')
            ? substr($fileNamespace, 4)
            : $fileNamespace;

        return rtrim($generatedNamespace, '\\') . '\\' . $suffix . '\\' . $serviceName;
    }

    public function getHandlerBaseClass(): string
    {
        return 'App\Platform\Http\Handler\AbstractProtobufRpcHandler';
    }

    public function getResponseHelperClass(): string
    {
        return 'App\Platform\Http\JsonResponse';
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

<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Descriptor\ProtoFileDescriptor;
use ProtoPhpGen\Generator\EndpointGenerator;
use ProtoPhpGen\Plugin\PluginOptions;
use ProtoPhpGen\Profile\EndpointProfile;
use ProtoPhpGen\Type\TypeResolver;

final class EndpointGeneratorTest extends TestCase
{
    public function testGeneratesEndpointAndHandlerFiles(): void
    {
        $generator = new EndpointGenerator(
            new PluginOptions(
                namespace: 'App\Generated\Endpoint',
                outputDir: 'gen',
            ),
            new BaseLikeEndpointProfile(),
        );

        $files = $generator->generateForProtoFile(
            ProtoFileDescriptor::fromArray([
                'name' => 'app/v1/health.proto',
                'package' => 'app.v1',
                'options' => [
                    'php_namespace' => 'App\Api\V1',
                ],
                'service' => [
                    [
                        'name' => 'HealthService',
                        'method' => [
                            [
                                'name' => 'Check',
                                'input_type' => '.app.v1.HealthCheckRequest',
                                'output_type' => '.app.v1.HealthCheckResponse',
                            ],
                        ],
                    ],
                ],
            ]),
            TypeResolver::fromProtoFiles([
                'app/v1/health.proto' => ProtoFileDescriptor::fromArray([
                    'name' => 'app/v1/health.proto',
                    'package' => 'app.v1',
                    'options' => [
                        'php_namespace' => 'App\Api\V1',
                    ],
                    'message_type' => [
                        [
                            'name' => 'HealthCheckRequest',
                        ],
                        [
                            'name' => 'HealthCheckResponse',
                        ],
                    ],
                ]),
            ]),
        );

        self::assertCount(2, $files);
        self::assertSame('gen/Generated/Endpoint/Api/V1/HealthService/CheckEndpoint.php', $files[0]->getName());
        self::assertStringContainsString('namespace App\Generated\Endpoint\Api\V1\HealthService;', $files[0]->getContent());
        self::assertStringContainsString('interface CheckEndpoint', $files[0]->getContent());
        self::assertStringContainsString('HealthCheckRequest', $files[0]->getContent());
        self::assertStringContainsString('HealthCheckResponse', $files[0]->getContent());

        self::assertSame('gen/Generated/Endpoint/Api/V1/HealthService/CheckHttpHandler.php', $files[1]->getName());
        self::assertStringContainsString('final readonly class CheckHttpHandler', $files[1]->getContent());
        self::assertStringContainsString('extends AbstractProtobufRpcHandler', $files[1]->getContent());
        self::assertStringContainsString('return $this->protobufResponse($response);', $files[1]->getContent());
    }

    public function testUsesEndpointProfileRuntimeBindings(): void
    {
        $generator = new EndpointGenerator(
            new PluginOptions(
                namespace: 'Vendor\Generated\Endpoint',
                outputDir: 'build',
            ),
            new StubEndpointProfile(),
        );

        $files = $generator->generateForProtoFile(
            ProtoFileDescriptor::fromArray([
                'name' => 'app/v1/health.proto',
                'package' => 'app.v1',
                'options' => [
                    'php_namespace' => 'Vendor\Api\V1',
                ],
                'service' => [
                    [
                        'name' => 'HealthService',
                        'method' => [
                            [
                                'name' => 'Check',
                                'input_type' => '.app.v1.HealthCheckRequest',
                                'output_type' => '.app.v1.HealthCheckResponse',
                            ],
                        ],
                    ],
                ],
            ]),
            TypeResolver::fromProtoFiles([
                'app/v1/health.proto' => ProtoFileDescriptor::fromArray([
                    'name' => 'app/v1/health.proto',
                    'package' => 'app.v1',
                    'options' => [
                        'php_namespace' => 'Vendor\Api\V1',
                    ],
                    'message_type' => [
                        [
                            'name' => 'HealthCheckRequest',
                        ],
                        [
                            'name' => 'HealthCheckResponse',
                        ],
                    ],
                ]),
            ]),
        );

        self::assertSame(
            'build/Vendor/Generated/Endpoint/Generated/Api/V1/HealthService/CheckHttpHandler.php',
            $files[1]->getName(),
        );
        self::assertStringContainsString('use Vendor\\Runtime\\Http\\CustomRpcHandler;', $files[1]->getContent());
        self::assertStringContainsString('use Vendor\\Runtime\\Http\\CustomResponseFactory;', $files[1]->getContent());
        self::assertStringContainsString('extends CustomRpcHandler', $files[1]->getContent());
        self::assertStringContainsString('parent::__construct($responseFactory);', $files[1]->getContent());
        self::assertStringContainsString('$message = $this->parseRequest($request, HealthCheckRequest::class);', $files[1]->getContent());
        self::assertStringContainsString('return $this->rejectInvalidRequest();', $files[1]->getContent());
        self::assertStringContainsString('return $this->writeResponse($response);', $files[1]->getContent());
    }
}

final readonly class StubEndpointProfile implements EndpointProfile
{
    public function getName(): string
    {
        return 'stub';
    }

    public function buildServiceNamespace(string $generatedNamespace, string $fileNamespace, string $serviceName): string
    {
        $suffix = str_starts_with($fileNamespace, 'Vendor\\')
            ? substr($fileNamespace, 7)
            : $fileNamespace;

        return rtrim($generatedNamespace, '\\') . '\\Generated\\' . $suffix . '\\' . $serviceName;
    }

    public function getHandlerBaseClass(): string
    {
        return 'Vendor\\Runtime\\Http\\CustomRpcHandler';
    }

    public function buildEndpointImplementationClass(string $fileNamespace, string $serviceName, string $methodName): string
    {
        $suffix = str_starts_with($fileNamespace, 'Vendor\\')
            ? substr($fileNamespace, 7)
            : $fileNamespace;

        return 'Vendor\\Runtime\\Endpoint\\' . $suffix . '\\' . $serviceName . '\\' . $methodName . 'Endpoint';
    }

    public function buildOperationRegistryNamespace(string $generatedNamespace, string $fileNamespace): string
    {
        $suffix = str_starts_with($fileNamespace, 'Vendor\\')
            ? substr($fileNamespace, 7)
            : $fileNamespace;

        return rtrim($generatedNamespace, '\\') . '\\Registry\\' . $suffix;
    }

    public function buildOperationRegistryClassName(string $sourceName): string
    {
        return 'StubOperationRegistry';
    }

    public function buildEndpointImplementationPath(
        string $sourceRoot,
        string $fileNamespace,
        string $serviceName,
        string $methodName,
    ): string {
        $suffix = str_starts_with($fileNamespace, 'Vendor\\')
            ? substr($fileNamespace, 7)
            : $fileNamespace;

        return rtrim($sourceRoot, '/')
            . '/Endpoint/'
            . str_replace('\\', '/', $suffix)
            . '/'
            . $serviceName
            . '/'
            . $methodName
            . 'Endpoint.php';
    }

    public function getResponseHelperClass(): string
    {
        return 'Vendor\\Runtime\\Http\\CustomResponseFactory';
    }

    public function getOperationDefinitionClass(): string
    {
        return 'Vendor\\Runtime\\Operation\\OperationDefinition';
    }

    public function getHttpOperationBindingClass(): string
    {
        return 'Vendor\\Runtime\\Operation\\HttpOperationBinding';
    }

    public function getOperationRegistryInterface(): string
    {
        return 'Vendor\\Runtime\\Operation\\OperationRegistry';
    }

    public function getResponseHelperParameterName(): string
    {
        return 'responseFactory';
    }

    public function getDecodeRequestMethodName(): string
    {
        return 'parseRequest';
    }

    public function getInvalidRequestResponseMethodName(): string
    {
        return 'rejectInvalidRequest';
    }

    public function getSuccessResponseMethodName(): string
    {
        return 'writeResponse';
    }
}

final readonly class BaseLikeEndpointProfile implements EndpointProfile
{
    public function getName(): string
    {
        return 'base_like';
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

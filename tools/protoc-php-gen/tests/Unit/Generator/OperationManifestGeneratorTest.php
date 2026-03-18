<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Descriptor\ProtoFileDescriptor;
use ProtoPhpGen\Generator\OperationManifestGenerator;
use ProtoPhpGen\Plugin\PluginOptions;
use ProtoPhpGen\Profile\BaseApiTemplateEndpointProfile;
use ProtoPhpGen\Type\TypeResolver;

final class OperationManifestGeneratorTest extends TestCase
{
    public function testGeneratesOperationManifest(): void
    {
        $generator = new OperationManifestGenerator(
            new PluginOptions(
                namespace: 'App\\Generated\\Transport',
                outputDir: 'gen',
                enabledModules: [OperationManifestGenerator::MODULE_NAME => true],
            ),
            new BaseApiTemplateEndpointProfile(),
        );

        $files = $generator->generateForProtoFile(
            ProtoFileDescriptor::fromArray([
                'name' => 'app/v1/health.proto',
                'package' => 'app.v1',
                'options' => [
                    'php_namespace' => 'App\\Api\\V1',
                ],
                'service' => [
                    [
                        'name' => 'HealthService',
                        'method' => [
                            [
                                'name' => 'Check',
                                'input_type' => '.app.v1.HealthCheckRequest',
                                'output_type' => '.app.v1.HealthCheckResponse',
                                'http_bindings' => [
                                    ['method' => 'GET', 'path' => '/api/v1/health'],
                                ],
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
                        'php_namespace' => 'App\\Api\\V1',
                    ],
                    'message_type' => [
                        ['name' => 'HealthCheckRequest'],
                        ['name' => 'HealthCheckResponse'],
                    ],
                    'service' => [
                        [
                            'name' => 'HealthService',
                            'method' => [
                                [
                                    'name' => 'Check',
                                    'input_type' => '.app.v1.HealthCheckRequest',
                                    'output_type' => '.app.v1.HealthCheckResponse',
                                    'http_bindings' => [
                                        ['method' => 'GET', 'path' => '/api/v1/health'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]),
            ]),
        );

        self::assertCount(1, $files);
        self::assertSame('gen/Generated/OperationManifest/app/v1/health.php', $files[0]->getName());
        self::assertStringContainsString("'service' => 'HealthService'", $files[0]->getContent());
        self::assertStringContainsString("'method' => 'Check'", $files[0]->getContent());
        self::assertStringContainsString("'operation_id' => 'HealthService.Check'", $files[0]->getContent());
        self::assertStringContainsString("'request_class' => 'App\\\\Api\\\\V1\\\\HealthCheckRequest'", $files[0]->getContent());
        self::assertStringContainsString("'response_class' => 'App\\\\Api\\\\V1\\\\HealthCheckResponse'", $files[0]->getContent());
        self::assertStringContainsString("'handler' => 'App\\\\Generated\\\\Transport\\\\Api\\\\V1\\\\HealthService\\\\CheckHttpHandler'", $files[0]->getContent());
        self::assertStringContainsString("'endpoint_interface' => 'App\\\\Generated\\\\Transport\\\\Api\\\\V1\\\\HealthService\\\\CheckEndpoint'", $files[0]->getContent());
        self::assertStringContainsString("'endpoint_implementation' => 'App\\\\Platform\\\\Http\\\\Endpoint\\\\Api\\\\V1\\\\HealthService\\\\CheckEndpoint'", $files[0]->getContent());
    }
}

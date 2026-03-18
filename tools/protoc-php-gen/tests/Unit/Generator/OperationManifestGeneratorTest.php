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
                namespace: 'App\\Generated\\Endpoint',
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
        self::assertSame('gen/Generated/OperationManifest/Api/V1/HealthOperationRegistry.php', $files[0]->getName());
        self::assertStringContainsString('namespace App\Generated\OperationManifest\Api\V1;', $files[0]->getContent());
        self::assertStringContainsString('use App\Platform\Http\Operation\HttpOperationBinding;', $files[0]->getContent());
        self::assertStringContainsString('use App\Platform\Http\Operation\OperationDefinition;', $files[0]->getContent());
        self::assertStringContainsString('use App\Platform\Http\Operation\OperationRegistry;', $files[0]->getContent());
        self::assertStringContainsString('final readonly class HealthOperationRegistry implements OperationRegistry', $files[0]->getContent());
        self::assertStringContainsString('public function getOperations(): array', $files[0]->getContent());
        self::assertStringContainsString("service: 'HealthService'", $files[0]->getContent());
        self::assertStringContainsString("method: 'Check'", $files[0]->getContent());
        self::assertStringContainsString("operationId: 'HealthService.Check'", $files[0]->getContent());
        self::assertStringContainsString("requestClass: 'App\\\\Api\\\\V1\\\\HealthCheckRequest'", $files[0]->getContent());
        self::assertStringContainsString("responseClass: 'App\\\\Api\\\\V1\\\\HealthCheckResponse'", $files[0]->getContent());
        self::assertStringContainsString("handler: 'App\\\\Generated\\\\Endpoint\\\\Api\\\\V1\\\\HealthService\\\\CheckHttpHandler'", $files[0]->getContent());
        self::assertStringContainsString("endpointInterface: 'App\\\\Generated\\\\Endpoint\\\\Api\\\\V1\\\\HealthService\\\\CheckEndpoint'", $files[0]->getContent());
        self::assertStringContainsString("endpointImplementation: 'App\\\\Platform\\\\Http\\\\Endpoint\\\\Api\\\\V1\\\\HealthService\\\\CheckEndpoint'", $files[0]->getContent());
    }
}

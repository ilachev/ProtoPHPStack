<?php

declare(strict_types=1);

namespace Tests\Unit\Tooling\ProtoPhpGen\Generator;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Descriptor\ProtoFileDescriptor;
use ProtoPhpGen\Generator\TransportContractGenerator;
use ProtoPhpGen\Plugin\PluginOptions;
use ProtoPhpGen\Profile\BaseApiTemplateTransportProfile;
use ProtoPhpGen\Type\TypeResolver;

final class TransportContractGeneratorTest extends TestCase
{
    public function testGeneratesEndpointAndHandlerFiles(): void
    {
        $generator = new TransportContractGenerator(
            new PluginOptions(
                namespace: 'App\Generated\Transport',
                outputDir: 'gen',
            ),
            new BaseApiTemplateTransportProfile(),
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

        self::assertCount(3, $files);
        self::assertSame('gen/Generated/Transport/Api/V1/HealthService/CheckEndpoint.php', $files[0]->getName());
        self::assertStringContainsString('namespace App\Generated\Transport\Api\V1\HealthService;', $files[0]->getContent());
        self::assertStringContainsString('interface CheckEndpoint', $files[0]->getContent());
        self::assertStringContainsString('HealthCheckRequest', $files[0]->getContent());
        self::assertStringContainsString('HealthCheckResponse', $files[0]->getContent());

        self::assertSame('gen/Generated/Transport/Api/V1/HealthService/CheckHttpHandler.php', $files[1]->getName());
        self::assertStringContainsString('final readonly class CheckHttpHandler', $files[1]->getContent());
        self::assertStringContainsString('extends AbstractProtobufRpcHandler', $files[1]->getContent());
        self::assertStringContainsString('return $this->protobufResponse($response);', $files[1]->getContent());

        self::assertSame('gen/Generated/EndpointBindings/app/v1/health.php', $files[2]->getName());
        self::assertStringContainsString(
            "'App\\\\Generated\\\\Transport\\\\Api\\\\V1\\\\HealthService\\\\CheckEndpoint' => 'App\\\\Platform\\\\Http\\\\Endpoint\\\\Api\\\\V1\\\\HealthService\\\\CheckEndpoint'",
            $files[2]->getContent(),
        );
    }
}

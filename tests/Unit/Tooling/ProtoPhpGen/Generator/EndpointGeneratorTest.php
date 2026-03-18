<?php

declare(strict_types=1);

namespace Tests\Unit\Tooling\ProtoPhpGen\Generator;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Descriptor\ProtoFileDescriptor;
use ProtoPhpGen\Generator\EndpointGenerator;
use ProtoPhpGen\Plugin\PluginOptions;
use ProtoPhpGen\Profile\BaseApiTemplateEndpointProfile;
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
            new BaseApiTemplateEndpointProfile(),
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
}

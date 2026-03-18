<?php

declare(strict_types=1);

namespace Tests\Unit\Tooling\ProtoPhpGen\Generator;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Config\GeneratorConfig;
use ProtoPhpGen\Generator\TransportContractGenerator;

final class TransportContractGeneratorTest extends TestCase
{
    public function testGeneratesEndpointAndHandlerFiles(): void
    {
        $generator = new TransportContractGenerator(
            (new GeneratorConfig(
                namespace: 'App\Generated\Transport',
                outputDir: 'gen',
            ))->setGenerateTransportContracts(true),
        );

        $files = $generator->generateForProtoFile(
            [
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
            ],
            [
                '.app.v1.HealthCheckRequest' => 'App\Api\V1\HealthCheckRequest',
                '.app.v1.HealthCheckResponse' => 'App\Api\V1\HealthCheckResponse',
            ],
        );

        self::assertCount(2, $files);
        self::assertSame('gen/Generated/Transport/Api/V1/HealthService/CheckEndpoint.php', $files[0]->getName());
        self::assertStringContainsString('namespace App\Generated\Transport\Api\V1\HealthService;', $files[0]->getContent());
        self::assertStringContainsString('interface CheckEndpoint', $files[0]->getContent());
        self::assertStringContainsString('HealthCheckRequest', $files[0]->getContent());
        self::assertStringContainsString('HealthCheckResponse', $files[0]->getContent());

        self::assertSame('gen/Generated/Transport/Api/V1/HealthService/CheckHttpHandler.php', $files[1]->getName());
        self::assertStringContainsString('final readonly class CheckHttpHandler', $files[1]->getContent());
        self::assertStringContainsString('extends AbstractProtobufRpcHandler', $files[1]->getContent());
        self::assertStringContainsString('return $this->protobufResponse($response);', $files[1]->getContent());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Descriptor\ProtoFileDescriptor;
use ProtoPhpGen\PhpGeneratorPlugin;
use ProtoPhpGen\Protoc\PluginRequest;

final class GeneratorIntegrationTest extends TestCase
{
    public function testPluginGeneratesTransportContractsFromRequest(): void
    {
        $request = new PluginRequest();
        $request->setParameter('namespace=App\\Generated\\Transport,output_dir=gen,generate_transport_contracts=true');
        $request->addFileToGenerate('app/v1/health.proto');
        $request->addProtoFile(
            'app/v1/health.proto',
            ProtoFileDescriptor::fromArray([
                'name' => 'app/v1/health.proto',
                'package' => 'app.v1',
                'options' => [
                    'php_namespace' => 'App\\Api\\V1',
                ],
                'message_type' => [
                    [
                        'name' => 'HealthCheckRequest',
                    ],
                    [
                        'name' => 'HealthCheckResponse',
                    ],
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
        );

        $plugin = new PhpGeneratorPlugin();
        $response = $plugin->process($request);

        self::assertNull($response->getError());
        self::assertCount(2, $response->getFiles());
        self::assertSame(
            'gen/Generated/Transport/Api/V1/HealthService/CheckEndpoint.php',
            $response->getFiles()[0]->getName(),
        );
        self::assertSame(
            'gen/Generated/Transport/Api/V1/HealthService/CheckHttpHandler.php',
            $response->getFiles()[1]->getName(),
        );
    }
}

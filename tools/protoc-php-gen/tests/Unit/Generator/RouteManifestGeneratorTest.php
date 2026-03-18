<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Descriptor\ProtoFileDescriptor;
use ProtoPhpGen\Generator\RouteManifestGenerator;
use ProtoPhpGen\Plugin\PluginOptions;
use ProtoPhpGen\Profile\BaseApiTemplateTransportProfile;
use ProtoPhpGen\Type\TypeResolver;

final class RouteManifestGeneratorTest extends TestCase
{
    public function testGeneratesRouteManifestFromHttpBindings(): void
    {
        $protoFile = ProtoFileDescriptor::fromArray([
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
                            'http_bindings' => [
                                [
                                    'method' => 'GET',
                                    'path' => '/api/v1/health',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $generator = new RouteManifestGenerator(
            new PluginOptions(
                namespace: 'App\Generated\Transport',
                outputDir: 'gen',
                enabledModules: [RouteManifestGenerator::MODULE_NAME => true],
            ),
            new BaseApiTemplateTransportProfile(),
        );

        $files = $generator->generateForProtoFile(
            $protoFile,
            TypeResolver::fromProtoFiles([$protoFile->getName() => $protoFile]),
        );

        self::assertCount(1, $files);
        self::assertSame('gen/Generated/RouteManifest/app/v1/health.php', $files[0]->getName());
        self::assertStringContainsString('/api/v1/health', $files[0]->getContent());
        self::assertStringContainsString(
            "'App\\\\Generated\\\\Transport\\\\Api\\\\V1\\\\HealthService\\\\CheckHttpHandler'",
            $files[0]->getContent(),
        );
        self::assertStringContainsString('HealthService.Check', $files[0]->getContent());
    }
}

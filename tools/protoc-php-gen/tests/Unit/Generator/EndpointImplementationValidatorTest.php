<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Descriptor\ProtoFileDescriptor;
use ProtoPhpGen\Generator\EndpointImplementationValidator;
use ProtoPhpGen\Plugin\PluginOptions;
use ProtoPhpGen\Type\TypeResolver;
use Tests\Fixtures\BaseApiTemplateLikeEndpointProfile;

final class EndpointImplementationValidatorTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/proto-endpoint-validator-' . bin2hex(random_bytes(8));
        mkdir($this->tempDir, recursive: true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->tempDir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tempDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }

            if ($file->isDir()) {
                rmdir($file->getPathname());

                continue;
            }

            unlink($file->getPathname());
        }

        rmdir($this->tempDir);
    }

    public function testPassesWhenEndpointImplementationExists(): void
    {
        $this->writeEndpointImplementation(
            'Platform/Http/Endpoint/Api/V1/HealthService/CheckEndpoint.php',
            'App\\Platform\\Http\\Endpoint\\Api\\V1\\HealthService',
            'CheckEndpoint',
            '\\App\\Generated\\Transport\\Api\\V1\\HealthService\\CheckEndpoint',
        );

        $validator = $this->createValidator();
        $result = $validator->generateForProtoFile($this->createHealthProtoFile(), $this->createTypeResolver());

        self::assertSame([], $result);
    }

    public function testFailsWhenEndpointImplementationIsMissing(): void
    {
        $validator = $this->createValidator();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Missing handwritten endpoint implementation App\\Platform\\Http\\Endpoint\\Api\\V1\\HealthService\\CheckEndpoint',
        );

        $validator->generateForProtoFile($this->createHealthProtoFile(), $this->createTypeResolver());
    }

    public function testFailsWhenEndpointImplementationDeclaresWrongClass(): void
    {
        $this->writeEndpointImplementation(
            'Platform/Http/Endpoint/Api/V1/HealthService/CheckEndpoint.php',
            'App\\Platform\\Http\\Endpoint\\Api\\V1\\HealthService',
            'WrongEndpoint',
            '\\App\\Generated\\Transport\\Api\\V1\\HealthService\\CheckEndpoint',
        );

        $validator = $this->createValidator();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Endpoint implementation file ' . $this->tempDir . '/Platform/Http/Endpoint/Api/V1/HealthService/CheckEndpoint.php must declare App\\Platform\\Http\\Endpoint\\Api\\V1\\HealthService\\CheckEndpoint',
        );

        $validator->generateForProtoFile($this->createHealthProtoFile(), $this->createTypeResolver());
    }

    public function testFailsWhenEndpointImplementationDoesNotImplementExpectedInterface(): void
    {
        $this->writeEndpointImplementation(
            'Platform/Http/Endpoint/Api/V1/HealthService/CheckEndpoint.php',
            'App\\Platform\\Http\\Endpoint\\Api\\V1\\HealthService',
            'CheckEndpoint',
            '\\App\\Generated\\Transport\\Api\\V1\\SystemService\\DescribeEndpoint',
        );

        $validator = $this->createValidator();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Endpoint implementation App\\Platform\\Http\\Endpoint\\Api\\V1\\HealthService\\CheckEndpoint must implement App\\Generated\\Transport\\Api\\V1\\HealthService\\CheckEndpoint',
        );

        $validator->generateForProtoFile($this->createHealthProtoFile(), $this->createTypeResolver());
    }

    private function createValidator(): EndpointImplementationValidator
    {
        return new EndpointImplementationValidator(
            new PluginOptions(
                namespace: 'App\\Generated\\Transport',
                sourceRoot: $this->tempDir,
                enabledModules: [EndpointImplementationValidator::MODULE_NAME => true],
            ),
            new BaseApiTemplateLikeEndpointProfile(),
        );
    }

    private function createHealthProtoFile(): ProtoFileDescriptor
    {
        return ProtoFileDescriptor::fromArray([
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
                        ],
                    ],
                ],
            ],
        ]);
    }

    private function createTypeResolver(): TypeResolver
    {
        return TypeResolver::fromProtoFiles([
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
            ]),
        ]);
    }

    private function writeEndpointImplementation(
        string $relativePath,
        string $namespace,
        string $className,
        string $implementedInterface,
    ): void
    {
        $path = $this->tempDir . '/' . $relativePath;
        $directory = \dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, recursive: true);
        }

        file_put_contents(
            $path,
            "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\nfinal class {$className} implements {$implementedInterface}\n{\n}\n",
        );
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Type;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Descriptor\ProtoFileDescriptor;
use ProtoPhpGen\Type\TypeResolver;

final class TypeResolverTest extends TestCase
{
    public function testResolvesFileNamespaceAndFullyQualifiedTypes(): void
    {
        $protoFile = ProtoFileDescriptor::fromArray([
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
        ]);

        $resolver = TypeResolver::fromProtoFiles([
            $protoFile->getName() => $protoFile,
        ]);

        self::assertSame('App\\Api\\V1', $resolver->resolveFileNamespace($protoFile));
        self::assertSame(
            'App\\Api\\V1\\HealthCheckRequest',
            $resolver->resolveTypeClass('.app.v1.HealthCheckRequest'),
        );
        self::assertSame(
            'App\\Api\\V1\\HealthCheckResponse',
            $resolver->resolveTypeClass('.app.v1.HealthCheckResponse'),
        );
    }

    public function testResolvesNestedMessageTypes(): void
    {
        $protoFile = ProtoFileDescriptor::fromArray([
            'name' => 'app/v1/example.proto',
            'package' => 'app.v1',
            'options' => [
                'php_namespace' => 'App\\Api\\V1',
            ],
            'message_type' => [
                [
                    'name' => 'Outer',
                    'nested_type' => [
                        [
                            'name' => 'Inner',
                        ],
                    ],
                ],
            ],
        ]);

        $resolver = TypeResolver::fromProtoFiles([
            $protoFile->getName() => $protoFile,
        ]);

        self::assertSame(
            'App\\Api\\V1\\Outer\\Inner',
            $resolver->resolveTypeClass('.app.v1.Outer.Inner'),
        );
    }
}

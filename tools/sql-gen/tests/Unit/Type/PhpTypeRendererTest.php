<?php

declare(strict_types=1);

namespace Tests\Unit\Type;

use PHPUnit\Framework\TestCase;
use SqlGen\Model\ResolvedSqlParameter;
use SqlGen\Model\RowField;
use SqlGen\Type\PhpTypeFactory;
use SqlGen\Type\PhpTypeRenderer;

final class PhpTypeRendererTest extends TestCase
{
    public function testRendersRowShapeViaTyphoonType(): void
    {
        $renderer = new PhpTypeRenderer();

        $shape = $renderer->renderRowShape([
            new RowField('id', 'id', 'id', PhpTypeFactory::fromNativeType('string'), false),
            new RowField('user_id', 'user_id', 'userId', PhpTypeFactory::fromNativeType('int'), true),
        ]);

        self::assertSame("array{'id': string, 'user_id': null|int}", $shape);
    }

    public function testRendersEmptyParamsShapeViaTyphoonType(): void
    {
        $renderer = new PhpTypeRenderer();

        $shape = $renderer->renderParamsShape([]);

        self::assertSame('array{}', $shape);
    }

    public function testRendersParameterShapeViaTyphoonType(): void
    {
        $renderer = new PhpTypeRenderer();

        $shape = $renderer->renderParamsShape([
            new ResolvedSqlParameter('user_id', 'userId', 'BIGINT', PhpTypeFactory::fromNativeType('int'), true),
            new ResolvedSqlParameter('payload', 'payload', 'TEXT', PhpTypeFactory::fromNativeType('string'), false),
        ]);

        self::assertSame("array{'user_id': null|int, 'payload': string}", $shape);
    }

    public function testRendersNativeTypeAndSignature(): void
    {
        $renderer = new PhpTypeRenderer();

        self::assertSame('string', $renderer->renderNative(PhpTypeFactory::fromNativeType('string')));
        self::assertSame('null|int', $renderer->renderSignature(PhpTypeFactory::fromNativeType('int'), true));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Type;

use PHPUnit\Framework\TestCase;
use SqlGen\Model\ResolvedSqlParameter;
use SqlGen\Model\RowField;
use SqlGen\Type\PhpDocTypeRenderer;

final class PhpDocTypeRendererTest extends TestCase
{
    public function testRendersRowShapeViaTyphoonType(): void
    {
        $renderer = new PhpDocTypeRenderer();

        $shape = $renderer->renderRowShape([
            new RowField('id', 'id', 'id', 'string', false),
            new RowField('user_id', 'user_id', 'userId', 'int', true),
        ]);

        self::assertSame("array{'id': string, 'user_id': null|int}", $shape);
    }

    public function testRendersEmptyParamsShapeViaTyphoonType(): void
    {
        $renderer = new PhpDocTypeRenderer();

        $shape = $renderer->renderParamsShape([]);

        self::assertSame('array{}', $shape);
    }

    public function testRendersParameterShapeViaTyphoonType(): void
    {
        $renderer = new PhpDocTypeRenderer();

        $shape = $renderer->renderParamsShape([
            new ResolvedSqlParameter('user_id', 'userId', 'BIGINT', 'int', true),
            new ResolvedSqlParameter('payload', 'payload', 'TEXT', 'string', false),
        ]);

        self::assertSame("array{'user_id': null|int, 'payload': string}", $shape);
    }
}

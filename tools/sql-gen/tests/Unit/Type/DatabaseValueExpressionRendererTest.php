<?php

declare(strict_types=1);

namespace Tests\Unit\Type;

use PHPUnit\Framework\TestCase;
use SqlGen\Type\DatabaseValueExpressionRenderer;

use const Typhoon\Type\boolT;
use const Typhoon\Type\floatT;
use const Typhoon\Type\intT;
use const Typhoon\Type\stringT;

final class DatabaseValueExpressionRendererTest extends TestCase
{
    public function testRendersNonNullableStringExpression(): void
    {
        $renderer = new DatabaseValueExpressionRenderer();

        self::assertSame(
            "array_key_exists('payload', \$row) && \$row['payload'] !== null ? (string) \$row['payload'] : throw new \\InvalidArgumentException('Missing required column payload.')",
            $renderer->renderArrayValue(stringT, '$row', 'payload', false),
        );
    }

    public function testRendersNullableIntExpression(): void
    {
        $renderer = new DatabaseValueExpressionRenderer();

        self::assertSame(
            "array_key_exists('user_id', \$row) && \$row['user_id'] !== null ? (int) \$row['user_id'] : null",
            $renderer->renderArrayValue(intT, '$row', 'user_id', true),
        );
    }

    public function testRendersAllSupportedScalarCasts(): void
    {
        $renderer = new DatabaseValueExpressionRenderer();

        self::assertStringContainsString('(bool)', $renderer->renderArrayValue(boolT, '$row', 'active', false));
        self::assertStringContainsString('(float)', $renderer->renderArrayValue(floatT, '$row', 'duration', false));
        self::assertStringContainsString('(int)', $renderer->renderArrayValue(intT, '$row', 'count', false));
        self::assertStringContainsString('(string)', $renderer->renderArrayValue(stringT, '$row', 'name', false));
    }
}

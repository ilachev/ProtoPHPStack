<?php

declare(strict_types=1);

namespace Tests\Unit\Type;

use PHPUnit\Framework\TestCase;
use SqlGen\Type\NativeTypeRenderer;

use const Typhoon\Type\intT;

use function Typhoon\Type\nullOrT;

use const Typhoon\Type\stringT;

final class NativeTypeRendererTest extends TestCase
{
    public function testRendersNativeScalarTypesFromTyphoonTypes(): void
    {
        $renderer = new NativeTypeRenderer();

        self::assertSame('int', $renderer->render(intT));
        self::assertSame('string', $renderer->render(stringT));
    }

    public function testRejectsUnsupportedComplexTypes(): void
    {
        $renderer = new NativeTypeRenderer();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported native PHP type rendering: null|string');

        $renderer->render(nullOrT(stringT));
    }
}

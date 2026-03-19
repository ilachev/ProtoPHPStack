<?php

declare(strict_types=1);

namespace Tests\Unit\Type;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Type\PhpDocTypeRenderer;

final class PhpDocTypeRendererTest extends TestCase
{
    public function testRendersListOfObjectsViaTyphoonType(): void
    {
        $renderer = new PhpDocTypeRenderer();

        self::assertSame('list<OperationDefinition>', $renderer->renderListOfObject('OperationDefinition'));
    }

    public function testRendersNamedObjectViaTyphoonType(): void
    {
        $renderer = new PhpDocTypeRenderer();

        self::assertSame('HealthCheckResponse', $renderer->renderNamedObject('HealthCheckResponse'));
    }

    public function testRendersClassStringViaTyphoonType(): void
    {
        $renderer = new PhpDocTypeRenderer();

        self::assertSame('class-string<CheckEndpoint>', $renderer->renderClassString('CheckEndpoint'));
    }
}

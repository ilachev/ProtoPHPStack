<?php

declare(strict_types=1);

namespace Tests\Unit\Tooling\ProtoPhpGen\Protoc;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Protoc\PluginRequest;

final class PluginRequestParametersTest extends TestCase
{
    public function testParsesProtocStyleCommaSeparatedParameters(): void
    {
        $request = new PluginRequest();
        $request->setParameter('namespace=App\Generated\Endpoint,output_dir=gen,generate_endpoints=true');

        self::assertTrue($request->hasParameter('namespace'));
        self::assertTrue($request->hasParameter('output_dir'));
        self::assertTrue($request->hasParameter('generate_endpoints'));
        self::assertSame('App\Generated\Endpoint', $request->getParameter('namespace'));
        self::assertSame('gen', $request->getParameter('output_dir'));
        self::assertSame('true', $request->getParameter('generate_endpoints'));
    }
}

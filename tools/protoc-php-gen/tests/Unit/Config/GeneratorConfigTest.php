<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Config\GeneratorConfig;

final class GeneratorConfigTest extends TestCase
{
    public function testConstructorWithDefaults(): void
    {
        $config = new GeneratorConfig();

        self::assertSame('App\\Gen', $config->getNamespace());
        self::assertSame('gen', $config->getOutputDir());
        self::assertFalse($config->shouldGenerateTransportContracts());
    }

    public function testSettersAndGetters(): void
    {
        $config = new GeneratorConfig();

        $result = $config->setNamespace('App\\Generated\\Transport');
        self::assertSame($config, $result);
        self::assertSame('App\\Generated\\Transport', $config->getNamespace());

        $result = $config->setOutputDir('build/gen');
        self::assertSame($config, $result);
        self::assertSame('build/gen', $config->getOutputDir());

        $result = $config->setGenerateTransportContracts(true);
        self::assertSame($config, $result);
        self::assertTrue($config->shouldGenerateTransportContracts());
    }
}

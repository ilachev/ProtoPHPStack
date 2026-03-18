<?php

declare(strict_types=1);

namespace Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Generator\EndpointImplementationValidator;
use ProtoPhpGen\Generator\OperationManifestGenerator;
use ProtoPhpGen\Generator\TransportContractGenerator;
use ProtoPhpGen\Plugin\PluginOptions;
use ProtoPhpGen\Profile\BaseApiTemplateTransportProfile;
use ProtoPhpGen\Protoc\PluginRequest;

final class PluginOptionsTest extends TestCase
{
    public function testConstructorWithDefaults(): void
    {
        $options = new PluginOptions();

        self::assertSame('App\Gen', $options->getNamespace());
        self::assertSame('gen', $options->getOutputDir());
        self::assertSame('src', $options->getSourceRoot());
        self::assertSame(BaseApiTemplateTransportProfile::NAME, $options->getTransportProfile());
        self::assertSame([], $options->getEnabledModules());
        self::assertFalse($options->isModuleEnabled(TransportContractGenerator::MODULE_NAME));
        self::assertFalse($options->isModuleEnabled(EndpointImplementationValidator::MODULE_NAME));
        self::assertFalse($options->isModuleEnabled(OperationManifestGenerator::MODULE_NAME));
    }

    public function testCreatesOptionsFromPluginRequest(): void
    {
        $request = new PluginRequest();
        $request->setParameter('namespace=App\Generated\Transport,output_dir=build/gen,source_root=app/src,transport_profile=base_api_template,generate_transport_contracts=true,generate_endpoint_validation=true,generate_operation_manifest=true');

        $options = PluginOptions::fromRequest($request);

        self::assertSame('App\Generated\Transport', $options->getNamespace());
        self::assertSame('build/gen', $options->getOutputDir());
        self::assertSame('app/src', $options->getSourceRoot());
        self::assertSame(BaseApiTemplateTransportProfile::NAME, $options->getTransportProfile());
        self::assertTrue($options->isModuleEnabled(TransportContractGenerator::MODULE_NAME));
        self::assertTrue($options->isModuleEnabled(EndpointImplementationValidator::MODULE_NAME));
        self::assertTrue($options->isModuleEnabled(OperationManifestGenerator::MODULE_NAME));
    }

    public function testKeepsTransportModuleDisabledWhenFlagIsFalse(): void
    {
        $request = new PluginRequest();
        $request->setParameter('generate_transport_contracts=false');

        $options = PluginOptions::fromRequest($request);

        self::assertFalse($options->isModuleEnabled(TransportContractGenerator::MODULE_NAME));
    }
}

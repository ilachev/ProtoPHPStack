<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Descriptor\ProtoFileDescriptor;
use ProtoPhpGen\Generator\CodeGeneratorModule;
use ProtoPhpGen\Generator\GeneratedFile;
use ProtoPhpGen\Generator\GeneratorRegistry;
use ProtoPhpGen\Plugin\PluginOptions;
use ProtoPhpGen\Type\TypeResolver;

final class GeneratorRegistryTest extends TestCase
{
    public function testReturnsOnlyEnabledModules(): void
    {
        $enabledModule = new StubGeneratorModule('endpoints', true);
        $disabledModule = new StubGeneratorModule('route_manifest', false);
        $registry = new GeneratorRegistry(
            [$enabledModule, $disabledModule],
            new PluginOptions(enabledModules: ['endpoints' => true]),
        );

        self::assertSame([$enabledModule], $registry->getEnabledModules());
    }

    public function testGeneratesFilesOnlyFromEnabledModules(): void
    {
        $enabledModule = new StubGeneratorModule('endpoints', true, [
            new GeneratedFile('gen/one.php', '<?php'),
        ]);
        $disabledModule = new StubGeneratorModule('route_manifest', false, [
            new GeneratedFile('gen/two.php', '<?php'),
        ]);
        $registry = new GeneratorRegistry(
            [$enabledModule, $disabledModule],
            new PluginOptions(enabledModules: ['endpoints' => true]),
        );

        $files = $registry->generateForProtoFile(
            ProtoFileDescriptor::fromArray([]),
            TypeResolver::fromProtoFiles([]),
        );

        self::assertCount(1, $files);
        self::assertSame('gen/one.php', $files[0]->getName());
    }
}

final readonly class StubGeneratorModule implements CodeGeneratorModule
{
    /**
     * @param list<GeneratedFile> $files
     */
    public function __construct(
        private string $name,
        private bool $enabled,
        private array $files = [],
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function isEnabled(PluginOptions $options): bool
    {
        return $this->enabled && $options->isModuleEnabled($this->name);
    }

    public function generateForProtoFile(ProtoFileDescriptor $protoFile, TypeResolver $typeResolver): array
    {
        return $this->files;
    }
}

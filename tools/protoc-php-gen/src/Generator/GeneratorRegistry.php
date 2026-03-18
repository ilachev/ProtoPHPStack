<?php

declare(strict_types=1);

namespace ProtoPhpGen\Generator;

use ProtoPhpGen\Descriptor\ProtoFileDescriptor;
use ProtoPhpGen\Plugin\PluginOptions;
use ProtoPhpGen\Type\TypeResolver;

final readonly class GeneratorRegistry
{
    /**
     * @param list<CodeGeneratorModule> $modules
     */
    public function __construct(
        private array $modules,
        private PluginOptions $options,
    ) {}

    /**
     * @return list<CodeGeneratorModule>
     */
    public function getEnabledModules(): array
    {
        return array_values(
            array_filter(
                $this->modules,
                fn(CodeGeneratorModule $module): bool => $module->isEnabled($this->options),
            ),
        );
    }

    /**
     * @return list<GeneratedFile>
     */
    public function generateForProtoFile(ProtoFileDescriptor $protoFile, TypeResolver $typeResolver): array
    {
        $files = [];

        foreach ($this->getEnabledModules() as $module) {
            array_push($files, ...$module->generateForProtoFile($protoFile, $typeResolver));
        }

        return $files;
    }
}

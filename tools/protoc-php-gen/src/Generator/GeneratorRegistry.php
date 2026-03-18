<?php

declare(strict_types=1);

namespace ProtoPhpGen\Generator;

use ProtoPhpGen\Plugin\PluginOptions;

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
     * @param array<string, mixed> $protoFile
     * @param array<string, class-string> $typeMap
     * @return list<GeneratedFile>
     */
    public function generateForProtoFile(array $protoFile, array $typeMap): array
    {
        $files = [];

        foreach ($this->getEnabledModules() as $module) {
            array_push($files, ...$module->generateForProtoFile($protoFile, $typeMap));
        }

        return $files;
    }
}

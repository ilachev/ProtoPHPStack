<?php

declare(strict_types=1);

namespace ProtoPhpGen\Generator;

use ProtoPhpGen\Plugin\PluginOptions;

interface CodeGeneratorModule
{
    public function getName(): string;

    public function isEnabled(PluginOptions $options): bool;

    /**
     * @param array<string, mixed> $protoFile
     * @param array<string, class-string> $typeMap
     * @return list<GeneratedFile>
     */
    public function generateForProtoFile(array $protoFile, array $typeMap): array;
}

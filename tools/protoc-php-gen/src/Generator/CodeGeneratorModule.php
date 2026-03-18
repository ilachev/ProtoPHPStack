<?php

declare(strict_types=1);

namespace ProtoPhpGen\Generator;

use ProtoPhpGen\Descriptor\ProtoFileDescriptor;
use ProtoPhpGen\Plugin\PluginOptions;

interface CodeGeneratorModule
{
    public function getName(): string;

    public function isEnabled(PluginOptions $options): bool;

    /**
     * @param array<string, class-string> $typeMap
     * @return list<GeneratedFile>
     */
    public function generateForProtoFile(ProtoFileDescriptor $protoFile, array $typeMap): array;
}

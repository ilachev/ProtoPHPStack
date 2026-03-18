<?php

declare(strict_types=1);

namespace ProtoPhpGen\Generator;

use ProtoPhpGen\Descriptor\ProtoFileDescriptor;
use ProtoPhpGen\Plugin\PluginOptions;
use ProtoPhpGen\Type\TypeResolver;

interface CodeGeneratorModule
{
    public function getName(): string;

    public function isEnabled(PluginOptions $options): bool;

    /**
     * @return list<GeneratedFile>
     */
    public function generateForProtoFile(ProtoFileDescriptor $protoFile, TypeResolver $typeResolver): array;
}

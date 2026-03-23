<?php

declare(strict_types=1);

namespace SqlGen\Config;

final readonly class GeneratorConfig
{
    public SqlRuntimeContracts $runtimeContracts;

    public function __construct(
        public string $inputDir,
        public string $outputDir,
        public string $namespace,
        public string $schemaPath,
        ?SqlRuntimeContracts $runtimeContracts = null,
    ) {
        $this->runtimeContracts = $runtimeContracts ?? SqlRuntimeContracts::fromNamespace('App\Platform\Storage\Sql');
    }
}

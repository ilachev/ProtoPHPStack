<?php

declare(strict_types=1);

namespace SqlGen\Config;

final readonly class GeneratorConfig
{
    public function __construct(
        public string $inputDir,
        public string $outputDir,
        public string $namespace,
    ) {}
}

<?php

declare(strict_types=1);

namespace SqlGen\Config;

final readonly class GeneratorConfig
{
    public SqlGenerationProfile $profile;

    public function __construct(
        public string $inputDir,
        public string $outputDir,
        public string $namespace,
        public string $schemaPath,
        ?SqlGenerationProfile $profile = null,
    ) {
        $this->profile = $profile ?? new SqlGenerationProfile();
    }
}

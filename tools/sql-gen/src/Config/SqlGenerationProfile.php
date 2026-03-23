<?php

declare(strict_types=1);

namespace SqlGen\Config;

interface SqlGenerationProfile
{
    public function runtimeContracts(): SqlRuntimeContracts;

    public function artifactNaming(): SqlArtifactNaming;
}

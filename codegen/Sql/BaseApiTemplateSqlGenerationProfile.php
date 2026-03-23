<?php

declare(strict_types=1);

namespace ProjectCodegen\Sql;

use SqlGen\Config\DefaultSqlGenerationProfile;
use SqlGen\Config\SqlGenerationProfile;
use SqlGen\Config\SqlRuntimeContracts;

final readonly class BaseApiTemplateSqlGenerationProfile implements SqlGenerationProfile
{
    private DefaultSqlGenerationProfile $profile;

    public function __construct()
    {
        $this->profile = new DefaultSqlGenerationProfile(
            runtimeContracts: SqlRuntimeContracts::fromNamespace('App\Platform\Storage\Sql'),
        );
    }

    #[\Override]
    public function runtimeContracts(): SqlRuntimeContracts
    {
        return $this->profile->runtimeContracts();
    }

    #[\Override]
    public function artifactNaming(): \SqlGen\Config\SqlArtifactNaming
    {
        return $this->profile->artifactNaming();
    }
}

<?php

declare(strict_types=1);

namespace SqlGen\Config;

final readonly class DefaultSqlGenerationProfile implements SqlGenerationProfile
{
    public function __construct(
        private SqlRuntimeContracts $runtimeContracts = new SqlRuntimeContracts(
            databaseRow: 'App\\Platform\\Storage\\Sql\\DatabaseRow',
            executableQuery: 'App\\Platform\\Storage\\Sql\\ExecutableQuery',
            oneRowQuery: 'App\\Platform\\Storage\\Sql\\OneRowQuery',
            manyRowsQuery: 'App\\Platform\\Storage\\Sql\\ManyRowsQuery',
            rowReturningQuery: 'App\\Platform\\Storage\\Sql\\RowReturningQuery',
        ),
        private SqlArtifactNaming $artifactNaming = new SqlArtifactNaming(),
    ) {}

    public static function withRuntimeNamespace(string $runtimeNamespace): self
    {
        return new self(
            runtimeContracts: SqlRuntimeContracts::fromNamespace($runtimeNamespace),
        );
    }

    #[\Override]
    public function runtimeContracts(): SqlRuntimeContracts
    {
        return $this->runtimeContracts;
    }

    #[\Override]
    public function artifactNaming(): SqlArtifactNaming
    {
        return $this->artifactNaming;
    }
}

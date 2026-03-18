<?php

declare(strict_types=1);

namespace ProtoPhpGen\Config;

final class GeneratorConfig
{
    public function __construct(
        private string $namespace = 'App\Gen',
        private string $outputDir = 'gen',
        private bool $generateTransportContracts = false,
    ) {}

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function setNamespace(string $namespace): self
    {
        $this->namespace = $namespace;

        return $this;
    }

    public function getOutputDir(): string
    {
        return $this->outputDir;
    }

    public function setOutputDir(string $outputDir): self
    {
        $this->outputDir = $outputDir;

        return $this;
    }

    public function shouldGenerateTransportContracts(): bool
    {
        return $this->generateTransportContracts;
    }

    public function setGenerateTransportContracts(bool $generateTransportContracts): self
    {
        $this->generateTransportContracts = $generateTransportContracts;

        return $this;
    }
}

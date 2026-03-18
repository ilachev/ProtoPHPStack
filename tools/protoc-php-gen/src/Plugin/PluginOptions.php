<?php

declare(strict_types=1);

namespace ProtoPhpGen\Plugin;

use ProtoPhpGen\Generator\RouteManifestGenerator;
use ProtoPhpGen\Generator\TransportContractGenerator;
use ProtoPhpGen\Profile\BaseApiTemplateTransportProfile;
use ProtoPhpGen\Protoc\PluginRequest;

final readonly class PluginOptions
{
    /**
     * @param array<string, bool> $enabledModules
     */
    public function __construct(
        private string $namespace = 'App\Gen',
        private string $outputDir = 'gen',
        private string $transportProfile = BaseApiTemplateTransportProfile::NAME,
        private array $enabledModules = [],
    ) {}

    public static function fromRequest(PluginRequest $request): self
    {
        $enabledModules = [];
        if ($request->hasParameter('generate_transport_contracts')) {
            $enabledModules[TransportContractGenerator::MODULE_NAME] = self::toBool(
                $request->getParameter('generate_transport_contracts'),
            );
        }

        if ($request->hasParameter('generate_route_manifest')) {
            $enabledModules[RouteManifestGenerator::MODULE_NAME] = self::toBool(
                $request->getParameter('generate_route_manifest'),
            );
        }

        return new self(
            namespace: $request->getParameter('namespace', 'App\Gen'),
            outputDir: $request->getParameter('output_dir', 'gen'),
            transportProfile: $request->getParameter('transport_profile', BaseApiTemplateTransportProfile::NAME),
            enabledModules: $enabledModules,
        );
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getOutputDir(): string
    {
        return $this->outputDir;
    }

    public function getTransportProfile(): string
    {
        return $this->transportProfile;
    }

    public function isModuleEnabled(string $moduleName): bool
    {
        return $this->enabledModules[$moduleName] ?? false;
    }

    /**
     * @return array<string, bool>
     */
    public function getEnabledModules(): array
    {
        return $this->enabledModules;
    }

    private static function toBool(string $value): bool
    {
        return match (strtolower($value)) {
            '1', 'true', 'yes', 'on' => true,
            default => false,
        };
    }
}

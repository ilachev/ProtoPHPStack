<?php

declare(strict_types=1);

namespace ProtoPhpGen\Descriptor;

final readonly class FileOptionsDescriptor
{
    public function __construct(
        private ?string $phpNamespace = null,
        private ?string $phpMetadataNamespace = null,
        private ?string $phpClassPrefix = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            phpNamespace: self::stringOrNull($data['php_namespace'] ?? null),
            phpMetadataNamespace: self::stringOrNull($data['php_metadata_namespace'] ?? null),
            phpClassPrefix: self::stringOrNull($data['php_class_prefix'] ?? null),
        );
    }

    public function getPhpNamespace(): ?string
    {
        return $this->phpNamespace;
    }

    public function getPhpMetadataNamespace(): ?string
    {
        return $this->phpMetadataNamespace;
    }

    public function getPhpClassPrefix(): ?string
    {
        return $this->phpClassPrefix;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return \is_string($value) && $value !== '' ? $value : null;
    }
}

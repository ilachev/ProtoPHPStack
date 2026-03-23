<?php

declare(strict_types=1);

namespace App\Platform\Cache;

final readonly class CacheConfig
{
    public function __construct(
        public string $engine,
        public string $address,
        public string $defaultPrefix,
        public string $namespaceSeed,
        public int $defaultTtl,
        public bool $compression = true,
        public int $compressionThreshold = 1024,
        public int $maxTtl = 604800,
        public int $fallbackMaxEntries = 1000,
    ) {}

    /**
     * @param array{
     *     engine: string,
     *     address: string,
     *     default_prefix: string,
     *     namespace_seed?: string,
     *     default_ttl: int,
     *     compression?: bool,
     *     compression_threshold?: int,
     *     max_ttl?: int,
     *     fallback_max_entries?: int,
     * } $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            engine: $config['engine'],
            address: $config['address'],
            defaultPrefix: $config['default_prefix'],
            namespaceSeed: $config['namespace_seed'] ?? '',
            defaultTtl: $config['default_ttl'],
            compression: $config['compression'] ?? true,
            compressionThreshold: $config['compression_threshold'] ?? 1024,
            maxTtl: $config['max_ttl'] ?? 604800,
            fallbackMaxEntries: $config['fallback_max_entries'] ?? 1000,
        );
    }
}

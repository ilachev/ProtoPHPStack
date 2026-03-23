<?php

declare(strict_types=1);

namespace App\Platform\Storage\Repository;

use App\Platform\Cache\ScopedCache;
use App\Platform\Logging\Logger;

abstract readonly class AbstractCachedRepository
{
    private const int CACHE_TTL = 3600;

    public function __construct(
        protected Logger $logger,
    ) {}

    protected function setCacheValue(ScopedCache $cache, string|int $identifier, mixed $value, ?int $ttl = null): void
    {
        $cache->set($identifier, $value, $ttl ?? self::CACHE_TTL);
        $this->logger->debug('Cache set', [
            'key' => $cache->key($identifier)->toString(),
            'repository' => static::class,
        ]);
    }

    protected function getCacheValue(ScopedCache $cache, string|int $identifier, mixed $default = null): mixed
    {
        if (!$cache->has($identifier)) {
            $this->logCacheMiss($cache, $identifier, 'not found');

            return $default;
        }

        $this->logCacheHit($cache, $identifier);

        return $cache->get($identifier, $default);
    }

    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    protected function getOrSetCacheValue(ScopedCache $cache, string|int $identifier, callable $callback, ?int $ttl = null): mixed
    {
        if ($cache->has($identifier)) {
            $this->logCacheHit($cache, $identifier);

            return $cache->get($identifier);
        }

        $result = $callback();
        $this->logCacheMiss($cache, $identifier, 'not found');
        $cache->set($identifier, $result, $ttl ?? self::CACHE_TTL);

        return $result;
    }

    protected function logCacheHit(ScopedCache $cache, string|int $identifier): void
    {
        $this->logger->debug('Cache hit', [
            'key' => $cache->key($identifier)->toString(),
            'repository' => static::class,
        ]);
    }

    protected function logCacheMiss(ScopedCache $cache, string|int $identifier, string $reason): void
    {
        $this->logger->debug('Cache miss', [
            'key' => $cache->key($identifier)->toString(),
            'reason' => $reason,
            'repository' => static::class,
        ]);
    }

    protected function deleteCacheValue(ScopedCache $cache, string|int $identifier): void
    {
        $cache->delete($identifier);
        $this->logger->debug('Cache delete', [
            'key' => $cache->key($identifier)->toString(),
            'repository' => static::class,
        ]);
    }

    protected function hasCacheValue(ScopedCache $cache, string|int $identifier): bool
    {
        $exists = $cache->has($identifier);

        if ($exists) {
            $this->logCacheHit($cache, $identifier);
        } else {
            $this->logCacheMiss($cache, $identifier, 'not found');
        }

        return $exists;
    }
}

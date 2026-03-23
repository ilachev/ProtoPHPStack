<?php

declare(strict_types=1);

namespace App\Platform\Storage\Repository;

use App\Platform\Cache\CacheService;
use App\Platform\Logging\Logger;

abstract readonly class AbstractCachedRepository
{
    private const int CACHE_TTL = 3600;

    public function __construct(
        protected CacheService $cache,
        protected Logger $logger,
        protected string $cacheKeyPrefix = '',
    ) {}

    protected function setCacheValue(string $key, mixed $value, ?int $ttl = null): void
    {
        if (!$this->cache->isAvailable()) {
            return;
        }

        $prefixedKey = $this->buildCacheKey($key);
        $this->cache->set($prefixedKey, $value, $ttl ?? self::CACHE_TTL);
        $this->logger->debug('Cache set', [
            'key' => $key,
            'repository' => static::class,
        ]);
    }

    protected function getCacheValue(string $key, mixed $default = null): mixed
    {
        if (!$this->cache->isAvailable()) {
            $this->logCacheMiss($key, 'cache unavailable');

            return $default;
        }

        $prefixedKey = $this->buildCacheKey($key);
        if (!$this->cache->has($prefixedKey)) {
            $this->logCacheMiss($key, 'not found');

            return $default;
        }

        $this->logCacheHit($key);

        return $this->cache->get($prefixedKey, $default);
    }

    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    protected function getOrSetCacheValue(string $key, callable $callback, ?int $ttl = null): mixed
    {
        if (!$this->cache->isAvailable()) {
            $result = $callback();
            $this->logCacheMiss($key, 'cache unavailable');

            return $result;
        }

        $prefixedKey = $this->buildCacheKey($key);
        if ($this->cache->has($prefixedKey)) {
            $this->logCacheHit($key);

            return $this->cache->get($prefixedKey);
        }

        $result = $callback();
        $this->logCacheMiss($key, 'not found');
        $this->cache->set($prefixedKey, $result, $ttl ?? self::CACHE_TTL);

        return $result;
    }

    protected function logCacheHit(string $key): void
    {
        $this->logger->debug('Cache hit', [
            'key' => $key,
            'repository' => static::class,
        ]);
    }

    protected function logCacheMiss(string $key, string $reason): void
    {
        $this->logger->debug('Cache miss', [
            'key' => $key,
            'reason' => $reason,
            'repository' => static::class,
        ]);
    }

    protected function buildCacheKey(string $key): string
    {
        return $this->cacheKeyPrefix . $key;
    }

    protected function deleteCacheValue(string $key): void
    {
        if (!$this->cache->isAvailable()) {
            return;
        }

        $prefixedKey = $this->buildCacheKey($key);
        $this->cache->delete($prefixedKey);
        $this->logger->debug('Cache delete', [
            'key' => $key,
            'repository' => static::class,
        ]);
    }

    protected function hasCacheValue(string $key): bool
    {
        if (!$this->cache->isAvailable()) {
            $this->logCacheMiss($key, 'cache unavailable');

            return false;
        }

        $prefixedKey = $this->buildCacheKey($key);
        $exists = $this->cache->has($prefixedKey);

        if ($exists) {
            $this->logCacheHit($key);
        } else {
            $this->logCacheMiss($key, 'not found');
        }

        return $exists;
    }
}

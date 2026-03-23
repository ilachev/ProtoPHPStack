<?php

declare(strict_types=1);

namespace App\Platform\Storage\Repository;

use App\Platform\Cache\CacheKey;
use App\Platform\Cache\CacheService;
use App\Platform\Logging\Logger;

abstract readonly class AbstractCachedRepository
{
    private const int CACHE_TTL = 3600;

    public function __construct(
        protected CacheService $cache,
        protected Logger $logger,
    ) {}

    protected function setCacheValue(CacheKey $key, mixed $value, ?int $ttl = null): void
    {
        if (!$this->cache->isAvailable()) {
            return;
        }

        $this->cache->set($key, $value, $ttl ?? self::CACHE_TTL);
        $this->logger->debug('Cache set', [
            'key' => $key->toString(),
            'repository' => static::class,
        ]);
    }

    protected function getCacheValue(CacheKey $key, mixed $default = null): mixed
    {
        if (!$this->cache->isAvailable()) {
            $this->logCacheMiss($key, 'cache unavailable');

            return $default;
        }

        if (!$this->cache->has($key)) {
            $this->logCacheMiss($key, 'not found');

            return $default;
        }

        $this->logCacheHit($key);

        return $this->cache->get($key, $default);
    }

    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    protected function getOrSetCacheValue(CacheKey $key, callable $callback, ?int $ttl = null): mixed
    {
        if (!$this->cache->isAvailable()) {
            $result = $callback();
            $this->logCacheMiss($key, 'cache unavailable');

            return $result;
        }

        if ($this->cache->has($key)) {
            $this->logCacheHit($key);

            return $this->cache->get($key);
        }

        $result = $callback();
        $this->logCacheMiss($key, 'not found');
        $this->cache->set($key, $result, $ttl ?? self::CACHE_TTL);

        return $result;
    }

    protected function logCacheHit(CacheKey $key): void
    {
        $this->logger->debug('Cache hit', [
            'key' => $key->toString(),
            'repository' => static::class,
        ]);
    }

    protected function logCacheMiss(CacheKey $key, string $reason): void
    {
        $this->logger->debug('Cache miss', [
            'key' => $key->toString(),
            'reason' => $reason,
            'repository' => static::class,
        ]);
    }

    protected function deleteCacheValue(CacheKey $key): void
    {
        if (!$this->cache->isAvailable()) {
            return;
        }

        $this->cache->delete($key);
        $this->logger->debug('Cache delete', [
            'key' => $key->toString(),
            'repository' => static::class,
        ]);
    }

    protected function hasCacheValue(CacheKey $key): bool
    {
        if (!$this->cache->isAvailable()) {
            $this->logCacheMiss($key, 'cache unavailable');

            return false;
        }

        $exists = $this->cache->has($key);

        if ($exists) {
            $this->logCacheHit($key);
        } else {
            $this->logCacheMiss($key, 'not found');
        }

        return $exists;
    }
}

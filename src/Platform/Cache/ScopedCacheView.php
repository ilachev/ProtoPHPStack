<?php

declare(strict_types=1);

namespace App\Platform\Cache;

final readonly class ScopedCacheView implements ScopedCache
{
    public function __construct(
        private CacheService $cache,
        private CacheScope $scope,
    ) {}

    public function key(string|int $identifier): CacheKey
    {
        return $this->scope->key($identifier);
    }

    public function set(string|int $identifier, mixed $value, ?int $ttl = null): bool
    {
        return $this->cache->set($this->key($identifier), $value, $ttl);
    }

    public function get(string|int $identifier, mixed $default = null): mixed
    {
        return $this->cache->get($this->key($identifier), $default);
    }

    public function has(string|int $identifier): bool
    {
        return $this->cache->has($this->key($identifier));
    }

    public function delete(string|int $identifier): bool
    {
        return $this->cache->delete($this->key($identifier));
    }

    public function invalidate(): bool
    {
        return $this->cache->invalidateScope($this->scope);
    }

    public function getOrSet(string|int $identifier, callable $callback, ?int $ttl = null): mixed
    {
        return $this->cache->getOrSet($this->key($identifier), $callback, $ttl);
    }
}

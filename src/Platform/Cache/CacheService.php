<?php

declare(strict_types=1);

namespace App\Platform\Cache;

interface CacheService
{
    /**
     * Checks whether the cache backend is available.
     */
    public function isAvailable(): bool;

    /**
     * Stores a value in cache.
     *
     * @template T
     * @param CacheKey|string $key Cache key
     * @param T $value Value to store
     * @param int|null $ttl Time to live in seconds, null for backend default
     * @return bool Operation result
     */
    public function set(CacheKey|string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * Reads a value from cache.
     *
     * @param CacheKey|string $key Cache key
     * @param mixed $default Fallback value
     * @return mixed Cached value or fallback value
     */
    public function get(CacheKey|string $key, mixed $default = null): mixed;

    /**
     * Checks whether a cache key exists.
     *
     * @param CacheKey|string $key Cache key
     * @return bool Whether the key exists
     */
    public function has(CacheKey|string $key): bool;

    /**
     * Deletes a cached value.
     *
     * @param CacheKey|string $key Cache key
     * @return bool Operation result
     */
    public function delete(CacheKey|string $key): bool;

    /**
     * Invalidates the current cache namespace.
     *
     * @return bool Operation result
     */
    public function clear(): bool;

    /**
     * Gets a value from cache or computes and stores it.
     *
     * @template T of mixed
     * @param CacheKey|string $key Cache key
     * @param callable():T $callback Value factory
     * @param int|null $ttl Time to live in seconds, null for backend default
     * @return T Cached or computed value
     */
    public function getOrSet(CacheKey|string $key, callable $callback, ?int $ttl = null): mixed;
}

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
     * @param string $key Cache key
     * @param T $value Value to store
     * @param int|null $ttl Time to live in seconds, null for backend default
     * @return bool Operation result
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * Reads a value from cache.
     *
     * @param string $key Cache key
     * @param mixed $default Fallback value
     * @return mixed Cached value or fallback value
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Checks whether a cache key exists.
     *
     * @param string $key Cache key
     * @return bool Whether the key exists
     */
    public function has(string $key): bool;

    /**
     * Deletes a cached value.
     *
     * @param string $key Cache key
     * @return bool Operation result
     */
    public function delete(string $key): bool;

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
     * @param string $key Cache key
     * @param callable():T $callback Value factory
     * @param int|null $ttl Time to live in seconds, null for backend default
     * @return T Cached or computed value
     */
    public function getOrSet(string $key, callable $callback, ?int $ttl = null): mixed;
}

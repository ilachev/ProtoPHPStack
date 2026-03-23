<?php

declare(strict_types=1);

namespace App\Platform\Cache;

interface ScopedCache
{
    public function key(string|int $identifier): CacheKey;

    /**
     * @template T
     * @param T $value
     */
    public function set(string|int $identifier, mixed $value, ?int $ttl = null): bool;

    public function get(string|int $identifier, mixed $default = null): mixed;

    public function has(string|int $identifier): bool;

    public function delete(string|int $identifier): bool;

    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public function getOrSet(string|int $identifier, callable $callback, ?int $ttl = null): mixed;
}

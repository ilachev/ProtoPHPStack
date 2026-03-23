<?php

declare(strict_types=1);

namespace App\Platform\Cache;

final readonly class ScopedCacheFactory
{
    public function __construct(
        private CacheService $cache,
    ) {}

    public function scope(CacheScope|string $scope): ScopedCache
    {
        $resolvedScope = \is_string($scope) ? new CacheScope($scope) : $scope;

        return new ScopedCacheView($this->cache, $resolvedScope);
    }
}

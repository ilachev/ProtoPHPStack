<?php

declare(strict_types=1);

namespace App\Platform\Cache;

final readonly class CacheScope
{
    public function __construct(
        public string $name,
    ) {}

    public function key(string|int $identifier): CacheKey
    {
        return new CacheKey($this, (string) $identifier);
    }
}

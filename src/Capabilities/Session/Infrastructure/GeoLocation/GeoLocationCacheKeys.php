<?php

declare(strict_types=1);

namespace App\Capabilities\Session\Infrastructure\GeoLocation;

use App\Platform\Cache\CacheKey;
use App\Platform\Cache\CacheScope;

final readonly class GeoLocationCacheKeys
{
    public function ipAddress(string $ip): CacheKey
    {
        return $this->scope()->key($ip);
    }

    private function scope(): CacheScope
    {
        return new CacheScope('geo_ip');
    }
}

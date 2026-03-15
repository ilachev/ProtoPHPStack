<?php

declare(strict_types=1);

namespace App\Capabilities\Session\Application;

/**
 * Contract for looking up geolocation by IP address.
 */
interface GeoLocationService
{
    /**
     * Returns geolocation data for an IP address.
     */
    public function getLocationByIp(string $ip): ?GeoLocationData;
}

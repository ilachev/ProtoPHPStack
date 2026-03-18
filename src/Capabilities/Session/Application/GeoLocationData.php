<?php

declare(strict_types=1);

namespace App\Capabilities\Session\Application;

/**
 * IP geolocation payload.
 */
final readonly class GeoLocationData
{
    public function __construct(
        public string $country,
        public string $countryCode,
        public string $region,
        public string $city,
        public string $zip,
        public float $lat,
        public float $lon,
        public string $timezone,
    ) {}

    /**
     * Creates an instance from raw API data.
     *
     * @param array<string, mixed> $data Raw geolocation API data
     */
    public static function fromArray(array $data): self
    {
        // Normalize scalar string fields from a permissive API response.
        $country = '';
        if (isset($data['country']) && (\is_string($data['country']) || is_numeric($data['country']))) {
            $country = (string) $data['country'];
        }

        $countryCode = '';
        if (isset($data['countryCode']) && (\is_string($data['countryCode']) || is_numeric($data['countryCode']))) {
            $countryCode = (string) $data['countryCode'];
        }

        $region = '';
        if (isset($data['region']) && (\is_string($data['region']) || is_numeric($data['region']))) {
            $region = (string) $data['region'];
        }

        $city = '';
        if (isset($data['city']) && (\is_string($data['city']) || is_numeric($data['city']))) {
            $city = (string) $data['city'];
        }

        $zip = '';
        if (isset($data['zip']) && (\is_string($data['zip']) || is_numeric($data['zip']))) {
            $zip = (string) $data['zip'];
        }

        $timezone = '';
        if (isset($data['timezone']) && (\is_string($data['timezone']) || is_numeric($data['timezone']))) {
            $timezone = (string) $data['timezone'];
        }

        // Normalize numeric coordinates from a permissive API response.
        $lat = 0.0;
        if (isset($data['lat']) && (\is_float($data['lat']) || \is_int($data['lat']) || (\is_string($data['lat']) && is_numeric($data['lat'])))) {
            $lat = (float) $data['lat'];
        }

        $lon = 0.0;
        if (isset($data['lon']) && (\is_float($data['lon']) || \is_int($data['lon']) || (\is_string($data['lon']) && is_numeric($data['lon'])))) {
            $lon = (float) $data['lon'];
        }

        return new self(
            country: $country,
            countryCode: $countryCode,
            region: $region,
            city: $city,
            zip: $zip,
            lat: $lat,
            lon: $lon,
            timezone: $timezone,
        );
    }
}

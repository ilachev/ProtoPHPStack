<?php

declare(strict_types=1);

namespace App\Capabilities\Session\Infrastructure\GeoLocation;

use App\Capabilities\Session\Application\GeoLocationData;

final readonly class IP2LocationRecord
{
    public function __construct(
        public string $countryCode,
        public string $countryName,
        public string $regionName,
        public string $cityName,
        public string $zipCode,
        public float $latitude,
        public float $longitude,
        public string $timeZone,
    ) {}

    /**
     * @param array<string, string|float|null> $record
     */
    public static function fromLookupResult(array $record): self
    {
        return new self(
            countryCode: self::stringField($record, 'countryCode'),
            countryName: self::stringField($record, 'countryName'),
            regionName: self::stringField($record, 'regionName'),
            cityName: self::stringField($record, 'cityName'),
            zipCode: self::stringField($record, 'zipCode'),
            latitude: self::floatField($record, 'latitude'),
            longitude: self::floatField($record, 'longitude'),
            timeZone: self::stringField($record, 'timeZone'),
        );
    }

    public function hasValidCountryData(): bool
    {
        return $this->countryCode !== '-'
            && $this->countryName !== '-'
            && $this->countryName !== '';
    }

    public function toGeoLocationData(): GeoLocationData
    {
        return new GeoLocationData(
            country: $this->countryName,
            countryCode: $this->countryCode,
            region: $this->regionName,
            city: $this->cityName,
            zip: $this->zipCode,
            lat: $this->latitude,
            lon: $this->longitude,
            timezone: $this->timeZone,
        );
    }

    /**
     * @param array<string, string|float|null> $record
     */
    private static function stringField(array $record, string $key): string
    {
        $value = $record[$key] ?? null;

        return $value === null ? '' : (string) $value;
    }

    /**
     * @param array<string, string|float|null> $record
     */
    private static function floatField(array $record, string $key): float
    {
        $value = $record[$key] ?? null;

        return $value === null ? 0.0 : (float) $value;
    }
}

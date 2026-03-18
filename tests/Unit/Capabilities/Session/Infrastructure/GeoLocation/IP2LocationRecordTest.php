<?php

declare(strict_types=1);

namespace Tests\Unit\Capabilities\Session\Infrastructure\GeoLocation;

use App\Capabilities\Session\Application\GeoLocationData;
use App\Capabilities\Session\Infrastructure\GeoLocation\IP2LocationRecord;
use PHPUnit\Framework\TestCase;

final class IP2LocationRecordTest extends TestCase
{
    public function testFromLookupResultNormalizesNullableFields(): void
    {
        $record = IP2LocationRecord::fromLookupResult([
            'countryCode' => 'US',
            'countryName' => 'United States',
            'regionName' => null,
            'cityName' => 'New York',
            'zipCode' => null,
            'latitude' => 40.7128,
            'longitude' => null,
            'timeZone' => '-05:00',
        ]);

        self::assertSame('US', $record->countryCode);
        self::assertSame('United States', $record->countryName);
        self::assertSame('', $record->regionName);
        self::assertSame('New York', $record->cityName);
        self::assertSame('', $record->zipCode);
        self::assertSame(40.7128, $record->latitude);
        self::assertSame(0.0, $record->longitude);
        self::assertSame('-05:00', $record->timeZone);
    }

    public function testHasValidCountryDataRejectsPlaceholderValues(): void
    {
        self::assertFalse(IP2LocationRecord::fromLookupResult([
            'countryCode' => '-',
            'countryName' => 'United States',
        ])->hasValidCountryData());

        self::assertFalse(IP2LocationRecord::fromLookupResult([
            'countryCode' => 'US',
            'countryName' => '-',
        ])->hasValidCountryData());

        self::assertFalse(IP2LocationRecord::fromLookupResult([
            'countryCode' => 'US',
            'countryName' => null,
        ])->hasValidCountryData());
    }

    public function testToGeoLocationDataCreatesTypedPayload(): void
    {
        $result = IP2LocationRecord::fromLookupResult([
            'countryCode' => 'US',
            'countryName' => 'United States',
            'regionName' => 'New York',
            'cityName' => 'New York',
            'zipCode' => '10001',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'timeZone' => '-05:00',
        ])->toGeoLocationData();

        self::assertEquals(
            new GeoLocationData(
                country: 'United States',
                countryCode: 'US',
                region: 'New York',
                city: 'New York',
                zip: '10001',
                lat: 40.7128,
                lon: -74.0060,
                timezone: '-05:00',
            ),
            $result,
        );
    }
}

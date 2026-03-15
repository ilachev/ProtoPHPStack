<?php

declare(strict_types=1);

namespace Tests\Integration\GeoLocation;

use App\Capabilities\Session\Application\GeoLocationConfig;
use App\Capabilities\Session\Application\GeoLocationService;
use Tests\Integration\IntegrationTestCase;

final class GeoLocationIntegrationTest extends IntegrationTestCase
{
    private GeoLocationService $geoLocationService;

    private GeoLocationConfig $geoLocationConfig;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var GeoLocationService $geoLocationService */
        $geoLocationService = $this->container->get(GeoLocationService::class);
        $this->geoLocationService = $geoLocationService;

        /** @var GeoLocationConfig $geoLocationConfig */
        $geoLocationConfig = $this->container->get(GeoLocationConfig::class);
        $this->geoLocationConfig = $geoLocationConfig;

        // Skip the suite when the GeoIP database is not available locally.
        if (!file_exists($this->geoLocationConfig->dbPath)) {
            self::markTestSkipped(
                "GeoIP database not found at {$this->geoLocationConfig->dbPath}",
            );
        }
    }

    /**
     * Ensures the service returns location data for known public IP addresses.
     */
    public function testGeoLocationServiceReturnsLocationData(): void
    {
        // Public IPs with stable geolocation records.
        $testIps = [
            '8.8.8.8',
            '77.88.55.77',
            '195.82.146.214',
        ];

        foreach ($testIps as $ip) {
            $location = $this->geoLocationService->getLocationByIp($ip);

            self::assertNotNull($location, "Expected geolocation data for IP {$ip}");

            self::assertNotEmpty($location->country, "Country should be present for IP {$ip}");
            self::assertNotEmpty($location->countryCode, "Country code should be present for IP {$ip}");

            self::assertGreaterThanOrEqual(-90, $location->lat, "Latitude should be >= -90 for IP {$ip}");
            self::assertLessThanOrEqual(90, $location->lat, "Latitude should be <= 90 for IP {$ip}");
            self::assertGreaterThanOrEqual(-180, $location->lon, "Longitude should be >= -180 for IP {$ip}");
            self::assertLessThanOrEqual(180, $location->lon, "Longitude should be <= 180 for IP {$ip}");
        }
    }

    /**
     * Ensures local addresses return the deterministic placeholder payload.
     */
    public function testGeoLocationServiceReturnsEasterEggForLocalIps(): void
    {
        $localIps = [
            '127.0.0.1',
            '192.168.1.1',
            '10.0.0.1',
            '172.16.0.1',
            '::1',
            'unknown',
        ];

        foreach ($localIps as $ip) {
            $location = $this->geoLocationService->getLocationByIp($ip);
            self::assertNotNull($location, "Local IP {$ip} should return placeholder geolocation data");
            self::assertEquals('Developer Land 🚀', $location->country, "Country should be Developer Land for IP {$ip}");
            self::assertEquals('DEV', $location->countryCode, "Country code should be DEV for IP {$ip}");
            self::assertEquals('Local Environment 💻', $location->region, "Region should contain the placeholder value for IP {$ip}");
            self::assertEquals('Localhost City 🏠', $location->city, "City should contain the placeholder value for IP {$ip}");
            self::assertEquals('UTC+Coffee ☕', $location->timezone, "Timezone should contain the placeholder value for IP {$ip}");
        }
    }

    /**
     * Ensures the service resolves expected country codes for known IP addresses.
     */
    public function testGeoLocationServiceReturnsCorrectCountry(): void
    {
        // Stable public IPs with expected country codes.
        $testCases = [
            ['8.8.8.8', 'US'],
            ['77.88.55.77', 'RU'],
            ['104.16.85.20', 'US'],
        ];

        foreach ($testCases as [$ip, $expectedCountryCode]) {
            $location = $this->geoLocationService->getLocationByIp($ip);

            self::assertNotNull($location, "Expected geolocation data for IP {$ip}");
            self::assertEquals(
                $expectedCountryCode,
                $location->countryCode,
                "Unexpected country code for IP {$ip}",
            );
        }
    }

    /**
     * Ensures invalid IPs do not produce geolocation data.
     */
    public function testGeoLocationServiceHandlesInvalidIps(): void
    {
        $invalidIps = [
            '999.999.999.999',
            'not-an-ip',
            '8.8.8',
            '',
        ];

        foreach ($invalidIps as $ip) {
            $location = $this->geoLocationService->getLocationByIp($ip);
            self::assertNull($location, "Invalid IP {$ip} should return null");
        }
    }
}

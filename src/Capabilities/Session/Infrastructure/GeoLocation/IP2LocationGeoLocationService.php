<?php

declare(strict_types=1);

namespace App\Capabilities\Session\Infrastructure\GeoLocation;

use App\Capabilities\Session\Application\GeoLocationConfig;
use App\Capabilities\Session\Application\GeoLocationData;
use App\Capabilities\Session\Application\GeoLocationService;
use App\Platform\Cache\ScopedCache;
use App\Platform\Cache\ScopedCacheFactory;
use App\Platform\Logging\Logger;
use IP2Location\Database;

/**
 * Geolocation service backed by the official IP2Location database.
 */
final readonly class IP2LocationGeoLocationService implements GeoLocationService
{
    private Database $db;

    private ScopedCache $ipAddressCache;

    public function __construct(
        private GeoLocationConfig $config,
        ScopedCacheFactory $scopedCacheFactory,
        private Logger $logger,
    ) {
        $this->db = new Database($this->config->dbPath, Database::FILE_IO);
        $this->ipAddressCache = $scopedCacheFactory->scope('geo_ip');
    }

    /**
     * Looks up geolocation data for an IP address.
     * @return GeoLocationData|null Geolocation data or null when the lookup fails
     */
    public function getLocationByIp(string $ip): ?GeoLocationData
    {
        // Return deterministic placeholder data for local and private addresses.
        if ($this->isLocalIp($ip) || $ip === 'unknown') {
            return new GeoLocationData(
                country: 'Developer Land 🚀',
                countryCode: 'DEV',
                region: 'Local Environment 💻',
                city: 'Localhost City 🏠',
                zip: '127001',
                lat: 42.0,
                lon: 42.0,
                timezone: 'UTC+Coffee ☕',
            );
        }

        // Reuse cached lookups to avoid repeated database reads.
        if ($this->ipAddressCache->has($ip)) {
            $cachedData = $this->ipAddressCache->get($ip);
            if ($cachedData instanceof GeoLocationData) {
                return $cachedData;
            }
        }

        try {
            // Read the full record from the IP2Location database.
            /** @var array<string, string|float|null> $record */
            $record = $this->db->lookup($ip, Database::ALL);
            $normalizedRecord = IP2LocationRecord::fromLookupResult($record);

            // Abort when the upstream database does not have meaningful country data.
            if (!$normalizedRecord->hasValidCountryData()) {
                $this->logger->debug('No valid geolocation data found for IP', ['ip' => $ip]);

                return null;
            }

            try {
                $geoData = $normalizedRecord->toGeoLocationData();

                // Cache successful lookups for subsequent requests.
                $this->ipAddressCache->set($ip, $geoData, $this->config->cacheTtl);

                return $geoData;
            } catch (\Throwable $e) {
                $this->logger->error('Error creating GeoLocationData object', [
                    'ip' => $ip,
                    'error' => $e->getMessage(),
                    'record' => $record,
                ]);

                return null;
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error getting geolocation data', [
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Returns true when the IP address is local or private.
     *
     * @param string $ip IP address to validate
     */
    private function isLocalIp(string $ip): bool
    {
        // Handle loopback addresses first.
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return true;
        }

        // Check private IPv4 ranges.
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $longIp = ip2long($ip);
            if ($longIp === false) {
                return true;
            }

            // 10.0.0.0/8
            if (($longIp & 0xFF000000) === 0x0A000000) {
                return true;
            }
            // 172.16.0.0/12
            if (($longIp & 0xFFF00000) === 0xAC100000) {
                return true;
            }
            // 192.168.0.0/16
            if (($longIp & 0xFFFF0000) === 0xC0A80000) {
                return true;
            }
        }

        // Check common private IPv6 ranges.
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // fc00::/7 - Unique Local Addresses
            if (strpos($ip, 'fc') === 0 || strpos($ip, 'fd') === 0) {
                return true;
            }
            // fe80::/10 - Link-Local Addresses
            if (strpos($ip, 'fe8') === 0 || strpos($ip, 'fe9') === 0
                || strpos($ip, 'fea') === 0 || strpos($ip, 'feb') === 0) {
                return true;
            }
        }

        return false;
    }
}

<?php

declare(strict_types=1);

namespace App\Capabilities\Session\Application;

/**
 * Configuration for the IP geolocation service.
 */
final readonly class GeoLocationConfig
{
    /**
     * @param string $dbPath Path to the IP2Location database file
     * @param string $downloadToken Token used to download database updates
     * @param string $downloadUrl URL used to download database updates
     * @param string $databaseCode Database code used by the download endpoint
     * @param int $cacheTtl Cache time-to-live in seconds
     */
    public function __construct(
        public string $dbPath,
        public string $downloadToken,
        public string $downloadUrl,
        public string $databaseCode,
        public int $cacheTtl = 3600,
    ) {}

    /**
     * Creates configuration from an associative array.
     *
     * @param array{
     *    db_path?: string,
     *    download_token?: string,
     *    download_url?: string,
     *    database_code?: string,
     *    cache_ttl?: int,
     * } $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            dbPath: $config['db_path'] ?? __DIR__ . '/../../../db/geoip/IP2LOCATION-LITE-DB11.BIN',
            downloadToken: $config['download_token'] ?? '',
            downloadUrl: $config['download_url'] ?? 'https://www.ip2location.com/download',
            databaseCode: $config['database_code'] ?? 'DB11LITEBIN',
            cacheTtl: $config['cache_ttl'] ?? 3600,
        );
    }

    /**
     * Returns the full database download URL.
     */
    public function getDownloadUrl(): string
    {
        return \sprintf(
            '%s/?token=%s&file=%s',
            rtrim($this->downloadUrl, '/'),
            $this->downloadToken,
            $this->databaseCode,
        );
    }
}

<?php

declare(strict_types=1);

use App\Capabilities\Session\Application\GeoLocationConfig;

return new GeoLocationConfig(
    dbPath: __DIR__ . '/../db/geoip/IP2LOCATION-LITE-DB11.BIN',
    downloadToken: getenv('IP2LOCATION_TOKEN') ?: '',
    downloadUrl: 'https://www.ip2location.com/download',
    databaseCode: 'DB11LITEBIN',
    cacheTtl: 3600,
);

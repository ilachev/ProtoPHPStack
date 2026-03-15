<?php

declare(strict_types=1);

namespace App\Capabilities\Session\Domain;

use App\Application\Client\GeoLocationData;
use ProtoPhpGen\Attributes\ProtoField;
use ProtoPhpGen\Attributes\ProtoMapping;

/**
 * DTO for storing client data in the session.
 */
#[ProtoMapping(class: 'App\Api\V1\SessionPayload')]
final readonly class SessionPayload
{
    public function __construct(
        #[ProtoField(name: 'ip')]
        public string $ip,
        #[ProtoField(name: 'user_agent')]
        public ?string $userAgent,
        #[ProtoField(name: 'accept_language')]
        public ?string $acceptLanguage,
        #[ProtoField(name: 'accept_encoding')]
        public ?string $acceptEncoding,
        #[ProtoField(name: 'x_forwarded_for')]
        public ?string $xForwardedFor,
        #[ProtoField(name: 'referer')]
        public ?string $referer,
        #[ProtoField(name: 'origin')]
        public ?string $origin,
        #[ProtoField(name: 'sec_ch_ua')]
        public ?string $secChUa,
        #[ProtoField(name: 'sec_ch_ua_platform')]
        public ?string $secChUaPlatform,
        #[ProtoField(name: 'sec_ch_ua_mobile')]
        public ?string $secChUaMobile,
        #[ProtoField(name: 'dnt')]
        public ?string $dnt,
        #[ProtoField(name: 'sec_fetch_dest')]
        public ?string $secFetchDest,
        #[ProtoField(name: 'sec_fetch_mode')]
        public ?string $secFetchMode,
        #[ProtoField(name: 'sec_fetch_site')]
        public ?string $secFetchSite,
        #[ProtoField(name: 'geo_location', type: 'json')]
        public ?GeoLocationData $geoLocation = null,
    ) {}

    public function isBrowser(): bool
    {
        if ($this->secChUa !== null || $this->secChUaMobile !== null) {
            return true;
        }

        if ($this->secFetchDest !== null || $this->secFetchMode !== null || $this->secFetchSite !== null) {
            return true;
        }

        if ($this->dnt !== null) {
            return true;
        }

        if ($this->userAgent === null) {
            return false;
        }

        $browserPatterns = [
            'Mozilla/',
            'Chrome/',
            'Safari/',
            'Firefox/',
            'Edge/',
            'MSIE',
            'Trident/',
            'Opera',
            'OPR/',
        ];

        foreach ($browserPatterns as $pattern) {
            if (str_contains($this->userAgent, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public function getDeviceType(): string
    {
        $deviceDesktop = 'desktop';
        $deviceMobile = 'mobile';
        $deviceTablet = 'tablet';
        $deviceUnknown = 'unknown';

        if ($this->secChUaMobile === '?1') {
            return $deviceMobile;
        }
        if ($this->secChUaMobile === '?0') {
            if ($this->secChUaPlatform !== null
                && (str_contains($this->secChUaPlatform, 'iPad')
                 || str_contains($this->secChUaPlatform, 'Android'))) {
                return $deviceTablet;
            }

            return $deviceDesktop;
        }

        if ($this->userAgent === null) {
            return $deviceUnknown;
        }

        $mobilePatterns = [
            'Mobile',
            'Android',
            'iPhone',
            'Windows Phone',
            'BlackBerry',
            'Opera Mini',
            'Opera Mobi',
            'webOS',
        ];

        $tabletPatterns = [
            'iPad',
            'Tablet',
            'Android(?!.*Mobile)',
            'Silk',
        ];

        foreach ($tabletPatterns as $pattern) {
            if (preg_match("/({$pattern})/i", $this->userAgent)) {
                return $deviceTablet;
            }
        }

        foreach ($mobilePatterns as $pattern) {
            if (str_contains($this->userAgent, $pattern)) {
                return $deviceMobile;
            }
        }

        return $deviceDesktop;
    }
}

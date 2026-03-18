<?php

declare(strict_types=1);

namespace App\Capabilities\Session\Domain;

use App\Capabilities\Session\Application\GeoLocationData;

/**
 * DTO for storing client data in the session.
 */
final readonly class SessionPayload
{
    public function __construct(
        public string $ip,
        public ?string $userAgent,
        public ?string $acceptLanguage,
        public ?string $acceptEncoding,
        public ?string $xForwardedFor,
        public ?string $referer,
        public ?string $origin,
        public ?string $secChUa,
        public ?string $secChUaPlatform,
        public ?string $secChUaMobile,
        public ?string $dnt,
        public ?string $secFetchDest,
        public ?string $secFetchMode,
        public ?string $secFetchSite,
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

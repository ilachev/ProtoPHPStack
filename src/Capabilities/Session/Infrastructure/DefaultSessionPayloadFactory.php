<?php

declare(strict_types=1);

namespace App\Capabilities\Session\Infrastructure;

use App\Application\Client\GeoLocationService;
use App\Capabilities\Session\Application\SessionPayloadFactory;
use App\Capabilities\Session\Domain\SessionPayload;
use Psr\Http\Message\ServerRequestInterface;

final readonly class DefaultSessionPayloadFactory implements SessionPayloadFactory
{
    public function __construct(
        private GeoLocationService $geoLocationService,
    ) {}

    public function createFromRequest(ServerRequestInterface $request): SessionPayload
    {
        $serverParams = $request->getServerParams();
        $ip = isset($serverParams['REMOTE_ADDR']) && \is_string($serverParams['REMOTE_ADDR'])
            ? $serverParams['REMOTE_ADDR']
            : 'unknown';

        $userAgent = $request->getHeaderLine('User-Agent') ?: null;
        $acceptLanguage = $request->getHeaderLine('Accept-Language') ?: null;
        $acceptEncoding = $request->getHeaderLine('Accept-Encoding') ?: null;
        $xForwardedFor = $request->getHeaderLine('X-Forwarded-For') ?: null;
        $referer = $request->getHeaderLine('Referer') ?: null;
        $origin = $request->getHeaderLine('Origin') ?: null;
        $secChUa = $request->getHeaderLine('Sec-CH-UA') ?: null;
        $secChUaPlatform = $request->getHeaderLine('Sec-CH-UA-Platform') ?: null;
        $secChUaMobile = $request->getHeaderLine('Sec-CH-UA-Mobile') ?: null;
        $dnt = $request->getHeaderLine('DNT') ?: null;
        $secFetchDest = $request->getHeaderLine('Sec-Fetch-Dest') ?: null;
        $secFetchMode = $request->getHeaderLine('Sec-Fetch-Mode') ?: null;
        $secFetchSite = $request->getHeaderLine('Sec-Fetch-Site') ?: null;
        $geoLocation = $this->geoLocationService->getLocationByIp($ip);

        return new SessionPayload(
            ip: $ip,
            userAgent: $userAgent,
            acceptLanguage: $acceptLanguage,
            acceptEncoding: $acceptEncoding,
            xForwardedFor: $xForwardedFor,
            referer: $referer,
            origin: $origin,
            secChUa: $secChUa,
            secChUaPlatform: $secChUaPlatform,
            secChUaMobile: $secChUaMobile,
            dnt: $dnt,
            secFetchDest: $secFetchDest,
            secFetchMode: $secFetchMode,
            secFetchSite: $secFetchSite,
            geoLocation: $geoLocation,
        );
    }

    public function createDefault(): SessionPayload
    {
        return new SessionPayload(
            ip: 'unknown',
            userAgent: null,
            acceptLanguage: null,
            acceptEncoding: null,
            xForwardedFor: null,
            referer: null,
            origin: null,
            secChUa: null,
            secChUaPlatform: null,
            secChUaMobile: null,
            dnt: null,
            secFetchDest: null,
            secFetchMode: null,
            secFetchSite: null,
            geoLocation: null,
        );
    }
}

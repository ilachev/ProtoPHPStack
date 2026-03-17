<?php

declare(strict_types=1);

namespace App\Capabilities\ApiStats\Transport\Http;

use App\Capabilities\ApiStats\Domain\ApiStat;
use App\Capabilities\ApiStats\Domain\ApiStatService;
use App\Capabilities\Session\Domain\Session;
use App\Capabilities\Session\Domain\SessionService;
use App\Platform\Http\Middleware;
use App\Platform\Http\RequestHandler;
use App\Platform\Logging\Logger;
use App\Platform\Routing\RouteResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ApiStatsMiddleware implements Middleware
{
    public function __construct(
        private ApiStatService $statsService,
        private SessionService $sessionService,
        private Logger $logger,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandler $handler,
    ): ResponseInterface {
        $startTime = hrtime(true);

        // SessionMiddleware attaches the current session to the request.
        /** @var Session|null $session */
        $session = $request->getAttribute('session');
        $sessionId = $session?->id;

        // Tests may pass sessionId directly when a full session object is unavailable.
        if ($sessionId === null && $request->getAttribute('sessionId') !== null) {
            $sessionIdAttr = $request->getAttribute('sessionId');
            $sessionId = \is_string($sessionIdAttr) ? $sessionIdAttr : null;
        }

        // Let the request complete before recording metrics.
        $response = $handler->handle($request);

        // Measure end-to-end request duration in milliseconds.
        $executionTime = (hrtime(true) - $startTime) / 1_000_000;

        // Resolve route metadata captured by routing middleware.
        /** @var RouteResult|null $routeResult */
        $routeResult = $request->getAttribute(RouteResult::class);
        $route = $request->getUri()->getPath(); // Fallback to the raw URI path.

        // Prefer the resolved handler name when routing succeeded.
        if ($routeResult !== null && $routeResult->isFound()) {
            $route = $routeResult->getHandler();
        }

        // Stats are tied to a session and skipped otherwise.
        if ($sessionId === null) {
            $this->logger->debug('ApiStatsMiddleware: Skipping stats - no sessionId');

            return $response;
        }

        // Explicit test sessionId bypasses repository validation.
        $isTestMode = $request->getAttribute('sessionId') !== null;

        // Production flow validates that the session still exists.
        if (!$isTestMode) {
            // SessionService is the source of truth for active sessions.
            $validSession = $this->sessionService->validateSession($sessionId);

            if ($validSession === null) {
                $this->logger->debug('ApiStatsMiddleware: Skipping stats - session does not exist', [
                    'session_id' => $sessionId,
                ]);

                return $response;
            }
        }

        $this->logger->debug('ApiStatsMiddleware: Saving API stats', [
            'session_id' => $sessionId,
            'route' => $route,
            'method' => $request->getMethod(),
            'test_mode' => $isTestMode,
        ]);

        // Persist a normalized API call record.
        $stat = new ApiStat(
            id: null,
            sessionId: $sessionId,
            route: $route,
            method: $request->getMethod(),
            statusCode: $response->getStatusCode(),
            executionTime: $executionTime,
            requestTime: time(),
        );

        // Save stats synchronously for now; a queue can be introduced later if needed.
        $this->statsService->saveApiCall($stat);

        return $response;
    }
}

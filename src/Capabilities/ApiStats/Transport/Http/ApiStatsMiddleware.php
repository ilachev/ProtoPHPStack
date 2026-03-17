<?php

declare(strict_types=1);

namespace App\Capabilities\ApiStats\Transport\Http;

use App\Capabilities\ApiStats\Domain\ApiCallRecorder;
use App\Capabilities\ApiStats\Domain\ApiStat;
use App\Capabilities\Session\Domain\Session;
use App\Platform\Http\Middleware;
use App\Platform\Http\RequestHandler;
use App\Platform\Routing\RouteResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ApiStatsMiddleware implements Middleware
{
    public function __construct(
        private ApiCallRecorder $recorder,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandler $handler,
    ): ResponseInterface {
        $startTime = hrtime(true);

        /** @var Session|null $session */
        $session = $request->getAttribute('session');
        $response = $handler->handle($request);
        $executionTime = (hrtime(true) - $startTime) / 1_000_000;
        /** @var RouteResult|null $routeResult */
        $routeResult = $request->getAttribute(RouteResult::class);
        $route = $request->getUri()->getPath();

        if ($routeResult !== null && $routeResult->isFound()) {
            $route = $routeResult->getHandler();
        }

        if ($session === null) {
            return $response;
        }
        $stat = new ApiStat(
            id: null,
            sessionId: $session->id,
            route: $route,
            method: $request->getMethod(),
            statusCode: $response->getStatusCode(),
            executionTime: $executionTime,
            requestTime: time(),
        );

        $this->recorder->record($stat);

        return $response;
    }
}

<?php

declare(strict_types=1);

namespace App\Platform\Http\Middleware;

use App\Platform\Http\Middleware;
use App\Platform\Http\RequestHandler;
use App\Platform\Logging\Logger;
use App\Platform\Logging\RoadRunnerLogger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class RequestMetricsMiddleware implements Middleware
{
    public function __construct(
        private Logger $logger,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandler $handler,
    ): ResponseInterface {
        $startTime = hrtime(true);
        $requestId = uniqid();

        // Attach requestId to the logger when the runtime supports it.
        if ($this->logger instanceof RoadRunnerLogger) {
            $this->logger->requestId = $requestId;
        }

        $response = $handler->handle(
            $request->withAttribute('requestId', $requestId),
        );

        $executionTime = (hrtime(true) - $startTime) / 1_000_000;

        return $response
            ->withHeader('X-Request-ID', $requestId)
            ->withHeader('X-Response-Time', \sprintf('%.2f ms', $executionTime))
            ->withHeader('Server-Timing', \sprintf('app;dur=%.2f', $executionTime));
    }
}

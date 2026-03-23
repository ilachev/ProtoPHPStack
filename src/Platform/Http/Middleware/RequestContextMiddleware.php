<?php

declare(strict_types=1);

namespace App\Platform\Http\Middleware;

use App\Platform\Http\HttpRuntimeConfig;
use App\Platform\Http\Middleware;
use App\Platform\Http\RequestContextAttributes;
use App\Platform\Http\RequestHandler;
use App\Platform\Logging\Logger;
use App\Platform\Logging\RoadRunnerLogger;
use App\Platform\Runtime\Deadline;
use App\Platform\Runtime\RequestContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class RequestContextMiddleware implements Middleware
{
    public function __construct(
        private Logger $logger,
        private HttpRuntimeConfig $config,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandler $handler,
    ): ResponseInterface {
        $startTime = hrtime(true);
        $context = new RequestContext(
            requestId: bin2hex(random_bytes(8)),
            deadline: Deadline::fromSeconds($this->config->requestTimeoutSeconds),
        );

        if ($this->logger instanceof RoadRunnerLogger) {
            $this->logger->requestId = $context->requestId;
        }

        $response = $handler->handle(RequestContextAttributes::attach($request, $context));
        $executionTime = (hrtime(true) - $startTime) / 1_000_000;

        return $response
            ->withHeader('X-Request-ID', $context->requestId)
            ->withHeader('X-Response-Time', \sprintf('%.2f ms', $executionTime))
            ->withHeader('Server-Timing', \sprintf('app;dur=%.2f', $executionTime));
    }
}

<?php

declare(strict_types=1);

namespace App\Platform\Http\Middleware;

use App\Platform\Http\Middleware;
use App\Platform\Http\RequestHandler;
use App\Platform\Logging\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class HttpLoggingMiddleware implements Middleware
{
    public function __construct(
        private Logger $logger,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandler $handler,
    ): ResponseInterface {
        $this->logger->info('Request.', [
            'request' => $request->getQueryParams(),
        ]);

        $response = $handler->handle($request);

        $this->logger->info('Application responded.', [
            'response' => $response->getBody()->getContents(),
        ]);

        return $response;
    }
}

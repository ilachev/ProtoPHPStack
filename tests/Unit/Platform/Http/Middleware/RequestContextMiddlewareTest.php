<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Http\Middleware;

use App\Platform\Http\Client\HttpRequestOptions;
use App\Platform\Http\HttpRuntimeConfig;
use App\Platform\Http\Middleware\RequestContextMiddleware;
use App\Platform\Http\RequestContextAttributes;
use App\Platform\Http\RequestHandler;
use App\Platform\Runtime\RequestContext;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Tests\Unit\Platform\Logging\TestLogger;

final class RequestContextMiddlewareTest extends TestCase
{
    public function testAttachesRequestContextAndResponseHeaders(): void
    {
        $middleware = new RequestContextMiddleware(
            new TestLogger(),
            new HttpRuntimeConfig(requestTimeoutSeconds: 5.0),
        );
        $handler = new class implements RequestHandler {
            public ?RequestContext $capturedContext = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->capturedContext = RequestContextAttributes::get($request);

                return new Response(200);
            }
        };
        $response = $middleware->process(
            new ServerRequest('GET', '/test'),
            $handler,
        );

        self::assertInstanceOf(RequestContext::class, $handler->capturedContext);
        self::assertSame(
            $handler->capturedContext->requestId,
            $response->getHeaderLine('X-Request-ID'),
        );
        self::assertNotSame('', $response->getHeaderLine('X-Response-Time'));
        self::assertNotSame('', $response->getHeaderLine('Server-Timing'));
    }

    public function testInheritsDeadlineIntoHttpRequestOptions(): void
    {
        $middleware = new RequestContextMiddleware(
            new TestLogger(),
            new HttpRuntimeConfig(requestTimeoutSeconds: 5.0),
        );
        $handler = new class implements RequestHandler {
            public ?HttpRequestOptions $resolvedOptions = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->resolvedOptions = RequestContextAttributes::inheritDeadline(
                    $request,
                    new HttpRequestOptions(timeoutSeconds: 2.0),
                );

                return new Response(200);
            }
        };

        $middleware->process(new ServerRequest('GET', '/test'), $handler);

        self::assertInstanceOf(HttpRequestOptions::class, $handler->resolvedOptions);
        self::assertNotNull($handler->resolvedOptions->deadline);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Capabilities\ApiStats\Transport\Http;

use App\Capabilities\ApiStats\Domain\ApiCallRecorder;
use App\Capabilities\ApiStats\Domain\ApiStat;
use App\Capabilities\ApiStats\Transport\Http\ApiStatsMiddleware;
use App\Capabilities\Session\Domain\Session;
use App\Platform\Http\RequestHandler;
use App\Platform\Routing\RouteResult;
use App\Platform\Routing\RouteStatus;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Tests\Unit\Capabilities\ApiStats\Domain\TestApiStatRepository;

final class ApiStatsMiddlewareTest extends TestCase
{
    private TestApiStatRepository $repository;

    private ApiCallRecorder $recorder;

    private ApiStatsMiddleware $middleware;

    protected function setUp(): void
    {
        $this->repository = new TestApiStatRepository();
        $this->recorder = new ApiCallRecorder($this->repository);
        $this->middleware = new ApiStatsMiddleware($this->recorder);
    }

    public function testProcessWithSession(): void
    {
        $sessionId = '0123456789abcdef0123456789abcdef';
        $routeName = 'test.route';
        $routePath = '/test/path';
        $method = 'GET';
        $statusCode = 200;
        $now = time();
        $session = new Session(
            id: $sessionId,
            userId: null,
            payload: '{}',
            expiresAt: $now + 3600,
            createdAt: $now,
            updatedAt: $now,
        );

        $routeResult = new RouteResult(RouteStatus::FOUND, $routeName, ['action' => 'test']);

        $request = new ServerRequest($method, 'https://example.com' . $routePath);
        $request = $request->withAttribute('session', $session)
            ->withAttribute(RouteResult::class, $routeResult);

        $response = new Response($statusCode);

        $handler = new class ($response) implements RequestHandler {
            private ResponseInterface $response;

            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        // Repository must be empty before middleware execution.
        self::assertEmpty($this->repository->stats);

        $result = $this->middleware->process($request, $handler);

        // Ensure middleware passed the request through.
        self::assertSame($response, $result);

        // Ensure API stats were saved.
        self::assertCount(1, $this->repository->stats);

        self::assertNotEmpty($this->repository->stats);
        $stat = $this->repository->stats[0];
        self::assertInstanceOf(ApiStat::class, $stat);
        self::assertSame($sessionId, $stat->sessionId);
        self::assertSame($routeName, $stat->route);
        self::assertSame($method, $stat->method);
        self::assertSame($statusCode, $stat->statusCode);
        self::assertGreaterThan(0, $stat->executionTime);
        // Ensure request time was recorded.
        self::assertGreaterThan(0, $stat->requestTime);
    }

    public function testProcessWithoutSession(): void
    {
        $request = new ServerRequest('GET', 'https://example.com/test');
        $response = new Response(200);

        $handler = new class ($response) implements RequestHandler {
            private ResponseInterface $response;

            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        $result = $this->middleware->process($request, $handler);

        // Ensure middleware passed the request through.
        self::assertSame($response, $result);

        // Ensure stats were not saved because the request has no session.
        self::assertEmpty($this->repository->stats);
    }

    public function testProcessWithoutRouteResult(): void
    {
        $sessionId = '0123456789abcdef0123456789abcdef';
        $path = '/no-route-result';
        $method = 'GET';
        $statusCode = 404;
        $now = time();
        $session = new Session(
            id: $sessionId,
            userId: null,
            payload: '{}',
            expiresAt: $now + 3600,
            createdAt: $now,
            updatedAt: $now,
        );

        $request = new ServerRequest($method, 'https://example.com' . $path);
        $request = $request->withAttribute('session', $session);

        $response = new Response($statusCode);

        $handler = new class ($response) implements RequestHandler {
            private ResponseInterface $response;

            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        $result = $this->middleware->process($request, $handler);

        // Ensure middleware passed the request through.
        self::assertSame($response, $result);

        // Ensure stats use the raw URI path when route metadata is missing.
        self::assertCount(1, $this->repository->stats);

        self::assertNotEmpty($this->repository->stats);
        $stat = $this->repository->stats[0];
        self::assertInstanceOf(ApiStat::class, $stat);
        self::assertSame($sessionId, $stat->sessionId);
        self::assertSame($path, $stat->route);
        self::assertSame($method, $stat->method);
        self::assertSame($statusCode, $stat->statusCode);
    }
}

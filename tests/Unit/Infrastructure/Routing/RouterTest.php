<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Routing;

use App\Platform\Routing\DefaultRouteCollector;
use App\Platform\Routing\RouteDefinitionInterface;
use App\Platform\Routing\Router;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class RouterTest extends TestCase
{
    private RouteDefinitionInterface&MockObject $routeDefinition;

    private Router $router;

    protected function setUp(): void
    {
        $this->routeDefinition = $this->createMock(RouteDefinitionInterface::class);

        // Set up the route definition mock to add routes to the collector.
        $this->routeDefinition
            ->method('defineRoutes')
            ->willReturnCallback(static function (DefaultRouteCollector $collector): void {
                $collector->addRoute('GET', '/api/test', 'TestHandler');
                $collector->addRoute('POST', '/api/test', 'TestPostHandler');
                $collector->addRoute('GET', '/api/users/{id}', 'UserHandler');
                $collector->addRoute('GET', '/api/posts/{slug}', 'PostHandler');
            });

        $this->router = new Router($this->routeDefinition);
    }

    public function testDispatchExactMatch(): void
    {
        $request = $this->createRequest('GET', '/api/test');

        $result = $this->router->dispatch($request);

        self::assertTrue($result->isFound());
        self::assertEquals('TestHandler', $result->getHandler());
        self::assertEquals([], $result->getParams());
    }

    public function testDispatchWithTrailingSlash(): void
    {
        $request = $this->createRequest('GET', '/api/test/');

        $result = $this->router->dispatch($request);

        self::assertTrue($result->isFound());
        self::assertEquals('TestHandler', $result->getHandler());
    }

    public function testDispatchMethodNotAllowed(): void
    {
        $request = $this->createRequest('PUT', '/api/test');

        $result = $this->router->dispatch($request);

        // Assert method not allowed.
        self::assertEquals(405, $result->getStatusCode());

        // Accessing handler for method-not-allowed must throw.
        $this->expectException(\RuntimeException::class);
        $result->getHandler();
    }

    public function testDispatchNotFound(): void
    {
        $request = $this->createRequest('GET', '/api/not-found');

        $result = $this->router->dispatch($request);

        self::assertEquals(404, $result->getStatusCode());
    }

    public function testDispatchWithParameters(): void
    {
        $request = $this->createRequest('GET', '/api/users/123');

        $result = $this->router->dispatch($request);

        self::assertTrue($result->isFound());
        self::assertEquals('UserHandler', $result->getHandler());
        self::assertEquals(['id' => '123'], $result->getParams());
    }

    public function testDispatchWithMultipleParameters(): void
    {
        // Set up a route definition with multiple path parameters.
        $routeDefinition = $this->createMock(RouteDefinitionInterface::class);
        $routeDefinition
            ->method('defineRoutes')
            ->willReturnCallback(static function (DefaultRouteCollector $collector): void {
                $collector->addRoute('GET', '/api/users/{id}/posts/{postId}', 'UserPostHandler');
            });

        $router = new Router($routeDefinition);
        $request = $this->createRequest('GET', '/api/users/123/posts/456');

        $result = $router->dispatch($request);

        self::assertTrue($result->isFound());
        self::assertEquals('UserPostHandler', $result->getHandler());
        self::assertEquals(['id' => '123', 'postId' => '456'], $result->getParams());
    }

    private function createRequest(string $method, string $uri): ServerRequestInterface
    {
        return new ServerRequest($method, $uri);
    }
}

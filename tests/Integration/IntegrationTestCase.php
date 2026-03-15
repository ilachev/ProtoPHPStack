<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\DI\Container;
use App\Platform\Runtime\App;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

abstract class IntegrationTestCase extends TestCase
{
    /**
     * @var Container<object>
     */
    protected Container $container;

    protected function setUp(): void
    {
        // Get the shared app instance created in bootstrap.php
        $app = TestAppFactory::getApp();
        $this->container = $app->getContainer();
    }

    /**
     * Creates a test request with the provided parameters.
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $uri Request URI
     * @param array<string, string> $headers Request headers
     * @param string|null $body Request body
     * @param array<string, string> $cookies Request cookies
     */
    protected function createRequest(
        string $method,
        string $uri,
        array $headers = [],
        ?string $body = null,
        array $cookies = [],
    ): ServerRequest {
        $request = new ServerRequest($method, $uri);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($body !== null) {
            $bodyStream = Stream::create($body);
            $request = $request->withBody($bodyStream);
        }

        if (!empty($cookies)) {
            $request = $request->withCookieParams($cookies);
        }

        return $request;
    }

    /**
     * Executes an HTTP request and returns the response.
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $uri Request URI
     * @param array<string, string> $headers Request headers
     * @param string|null $body Request body
     * @param array<string, string> $cookies Request cookies
     */
    protected function makeRequest(
        string $method,
        string $uri,
        array $headers = [],
        ?string $body = null,
        array $cookies = [],
    ): ResponseInterface {
        $request = $this->createRequest($method, $uri, $headers, $body, $cookies);

        // Use the application to process the request directly
        $app = TestAppFactory::getApp();

        return $app->handleRequest($request);
    }
}

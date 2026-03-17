<?php

declare(strict_types=1);

namespace App\Platform\Runtime;

use App\Capabilities\ApiStats\Transport\Http\ApiStatsMiddleware;
use App\Capabilities\Session\Transport\Http\SessionMiddleware;
use App\Infrastructure\Cache\CacheService;
use App\Infrastructure\Logger\Logger;
use App\Platform\DI\Container;
use App\Platform\DI\DIContainer;
use App\Platform\Http\Middleware\{
    ErrorHandlerMiddleware,
    HttpLoggingMiddleware,
    Pipeline,
    RequestMetricsMiddleware,
    RoutingMiddleware
};
use App\Platform\Http\RouteHandlerResolver;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Spiral\RoadRunner\Http\PSR7Worker;

/** @template T of object */
final readonly class App
{
    /** @var DIContainer<T> */
    private Container $container;

    private PSR7Worker $worker;

    private Pipeline $pipeline;

    /**
     * @return DIContainer<T>
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        return $this->pipeline->handle($request);
    }

    public function __construct(string $configPath)
    {
        /** @var callable(DIContainer<T>): void $containerConfig */
        $containerConfig = require $configPath;

        $this->container = new DIContainer();
        $containerConfig($this->container);

        $this->worker = $this->container->get(PSR7Worker::class);
        $this->pipeline = $this->createPipeline();
    }

    public function run(): void
    {
        // Clear cache once on startup to avoid carrying stale state between deployments.
        $this->clearAllCache();

        while (true) {
            $request = $this->worker->waitRequest();
            if ($request === null) {
                break;
            }

            $response = $this->handleRequest($request);
            $this->worker->respond($response);
        }
    }

    /**
     * Clears all cache storage on startup.
     */
    private function clearAllCache(): void
    {
        $cacheService = $this->container->get(CacheService::class);
        $logger = $this->container->get(Logger::class);

        try {
            $success = $cacheService->clear();

            if ($success) {
                $logger->info('Cache fully cleared on application startup');
            } else {
                $logger->warning('Cache clearing reported failure on startup without exception');
            }
        } catch (\Throwable $e) {
            $logger->error('Failed to clear cache on startup', [
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }

    private function createPipeline(): Pipeline
    {
        return new Pipeline(
            $this->container->get(RouteHandlerResolver::class),
            [
                $this->container->get(ErrorHandlerMiddleware::class),
                $this->container->get(RequestMetricsMiddleware::class),
                $this->container->get(SessionMiddleware::class),
                $this->container->get(ApiStatsMiddleware::class),
                $this->container->get(RoutingMiddleware::class),
                $this->container->get(HttpLoggingMiddleware::class),
            ],
        );
    }
}

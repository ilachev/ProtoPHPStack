<?php

declare(strict_types=1);

namespace App\Capabilities\ApiStats;

use App\Capabilities\ApiStats\Domain\ApiStatRepository;
use App\Capabilities\ApiStats\Domain\ApiStatService;
use App\Capabilities\ApiStats\Infrastructure\Persistence\SqlApiStatRepository;
use App\Capabilities\ApiStats\Transport\Http\ApiStatsMiddleware;
use App\Capabilities\Capability;
use App\Capabilities\Session\Domain\SessionService;
use App\Platform\DI\Container;
use App\Platform\Logging\Logger;

/**
 * @implements Capability<object>
 */
final readonly class ApiStatsModule implements Capability
{
    public function register(Container $container): void
    {
        $container->bind(SqlApiStatRepository::class, SqlApiStatRepository::class);

        $container->set(
            ApiStatRepository::class,
            static fn(Container $container): ApiStatRepository => $container->get(SqlApiStatRepository::class),
        );

        $container->bind(ApiStatService::class, ApiStatService::class);

        $container->set(
            ApiStatsMiddleware::class,
            static function (Container $container): ApiStatsMiddleware {
                /** @var ApiStatService $apiStatService */
                $apiStatService = $container->get(ApiStatService::class);

                /** @var SessionService $sessionService */
                $sessionService = $container->get(SessionService::class);

                /** @var Logger $logger */
                $logger = $container->get(Logger::class);

                return new ApiStatsMiddleware($apiStatService, $sessionService, $logger);
            },
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\ApiStats;

use App\Infrastructure\DI\Container;
use App\Infrastructure\Logger\Logger;
use App\Modules\ApiStats\Domain\ApiStatRepository;
use App\Modules\ApiStats\Domain\ApiStatService;
use App\Modules\ApiStats\Infrastructure\Persistence\PostgreSQLApiStatRepository;
use App\Modules\ApiStats\Transport\Http\ApiStatsMiddleware;
use App\Modules\Module;
use App\Modules\Session\Domain\SessionService;

/**
 * @implements Module<object>
 */
final readonly class ApiStatsModule implements Module
{
    public function register(Container $container): void
    {
        $container->bind(PostgreSQLApiStatRepository::class, PostgreSQLApiStatRepository::class);

        $container->set(
            ApiStatRepository::class,
            static fn(Container $container): ApiStatRepository => $container->get(PostgreSQLApiStatRepository::class),
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

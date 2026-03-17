<?php

declare(strict_types=1);

namespace App\Capabilities\ApiStats;

use App\Capabilities\ApiStats\Domain\ApiCallRecorder;
use App\Capabilities\ApiStats\Domain\ApiStatRepository;
use App\Capabilities\ApiStats\Infrastructure\Persistence\SqlApiStatRepository;
use App\Capabilities\ApiStats\Transport\Http\ApiStatsMiddleware;
use App\Capabilities\Capability;
use App\Platform\DI\Container;

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

        $container->bind(ApiCallRecorder::class, ApiCallRecorder::class);

        $container->set(
            ApiStatsMiddleware::class,
            static function (Container $container): ApiStatsMiddleware {
                /** @var ApiCallRecorder $recorder */
                $recorder = $container->get(ApiCallRecorder::class);

                return new ApiStatsMiddleware($recorder);
            },
        );
    }
}

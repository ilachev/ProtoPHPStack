<?php

declare(strict_types=1);

namespace App\Modules\Home;

use App\Application\Mappers\DataTransferObjectMapper;
use App\Infrastructure\DI\Container;
use App\Modules\Home\Domain\HomeService;
use App\Modules\Home\Transport\Http\HomeHandler;
use App\Modules\Home\Transport\Mapping\HomeResponseMapper;
use App\Modules\Module;
use App\Platform\Http\JsonResponse;

/**
 * @implements Module<object>
 */
final readonly class HomeModule implements Module
{
    public function register(Container $container): void
    {
        $container->bind(HomeService::class, HomeService::class);

        $container->set(
            HomeResponseMapper::class,
            static function (Container $container): HomeResponseMapper {
                /** @var DataTransferObjectMapper $dtoMapper */
                $dtoMapper = $container->get(DataTransferObjectMapper::class);

                return new HomeResponseMapper($dtoMapper);
            },
        );

        $container->set(
            HomeHandler::class,
            static function (Container $container): HomeHandler {
                /** @var HomeService $homeService */
                $homeService = $container->get(HomeService::class);

                /** @var HomeResponseMapper $homeResponseMapper */
                $homeResponseMapper = $container->get(HomeResponseMapper::class);

                /** @var JsonResponse $jsonResponse */
                $jsonResponse = $container->get(JsonResponse::class);

                return new HomeHandler($homeService, $homeResponseMapper, $jsonResponse);
            },
        );
    }
}

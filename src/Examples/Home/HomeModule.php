<?php

declare(strict_types=1);

namespace App\Examples\Home;

use App\Examples\ExampleModule;
use App\Examples\Home\Domain\HomeService;
use App\Examples\Home\Transport\Http\HomeHandler;
use App\Examples\Home\Transport\Mapping\HomeResponseMapper;
use App\Infrastructure\DI\Container;
use App\Platform\DataMapping\DataTransferObjectMapper;
use App\Platform\Http\JsonResponse;

/**
 * @implements ExampleModule<object>
 */
final readonly class HomeModule implements ExampleModule
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

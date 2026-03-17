<?php

declare(strict_types=1);

namespace App\Platform\DI\ServiceProviders;

use App\Infrastructure\Hydrator\DefaultJsonFieldAdapter;
use App\Infrastructure\Hydrator\Hydrator;
use App\Infrastructure\Hydrator\JsonFieldAdapter;
use App\Platform\DataMapping\DataTransferObjectMapper;
use App\Platform\DI\Container;
use App\Platform\DI\ServiceProvider;
use App\Platform\Http\JsonResponse;

/**
 * @implements ServiceProvider<object>
 */
final readonly class PlatformSupportServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        // JsonResponse
        $container->bind(JsonResponse::class, JsonResponse::class);

        // JSON field adapter
        $container->bind(JsonFieldAdapter::class, DefaultJsonFieldAdapter::class);
        $container->set(
            DefaultJsonFieldAdapter::class,
            static function (Container $container): DefaultJsonFieldAdapter {
                /** @var Hydrator $hydrator */
                $hydrator = $container->get(Hydrator::class);

                return new DefaultJsonFieldAdapter($hydrator);
            },
        );

        // DataTransferObjectMapper
        $container->set(
            DataTransferObjectMapper::class,
            static function (Container $container): DataTransferObjectMapper {
                /** @var Hydrator $hydrator */
                $hydrator = $container->get(Hydrator::class);

                return new DataTransferObjectMapper($hydrator);
            },
        );

    }
}

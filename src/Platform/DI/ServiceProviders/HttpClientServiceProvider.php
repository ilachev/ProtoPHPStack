<?php

declare(strict_types=1);

namespace App\Platform\DI\ServiceProviders;

use App\Platform\Config\ProjectPath;
use App\Platform\DI\Container;
use App\Platform\DI\ServiceProvider;
use App\Platform\Http\Client\CurlTransport;
use App\Platform\Http\Client\HttpClient;
use App\Platform\Http\Client\HttpTransport;
use App\Platform\Http\Client\ResilientHttpClient;
use App\Platform\Http\HttpRuntimeConfig;
use App\Platform\Logging\Logger;

/**
 * @implements ServiceProvider<object>
 */
final readonly class HttpClientServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        $container->set(
            HttpRuntimeConfig::class,
            static function (): HttpRuntimeConfig {
                /** @var HttpRuntimeConfig $config */
                $config = require ProjectPath::getConfigPath('http.php');

                return $config;
            },
        );

        $container->bind(HttpTransport::class, CurlTransport::class);

        $container->set(
            HttpClient::class,
            static function (Container $container): HttpClient {
                /** @var HttpTransport $transport */
                $transport = $container->get(HttpTransport::class);

                /** @var Logger $logger */
                $logger = $container->get(Logger::class);

                return new ResilientHttpClient($transport, $logger);
            },
        );
    }
}

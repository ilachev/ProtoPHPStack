<?php

declare(strict_types=1);

namespace App\Capabilities\Session;

use App\Application\Client\GeoLocationService;
use App\Capabilities\Capability;
use App\Capabilities\Session\Application\ClientConfig;
use App\Capabilities\Session\Application\ClientDetector;
use App\Capabilities\Session\Application\SessionPayloadFactory;
use App\Capabilities\Session\Domain\SessionConfig;
use App\Capabilities\Session\Domain\SessionRepository;
use App\Capabilities\Session\Domain\SessionService;
use App\Capabilities\Session\Infrastructure\DefaultSessionPayloadFactory;
use App\Capabilities\Session\Infrastructure\FingerprintClientDetector;
use App\Capabilities\Session\Infrastructure\Persistence\CachedSessionRepository;
use App\Capabilities\Session\Infrastructure\Persistence\PostgreSQLSessionRepository;
use App\Capabilities\Session\Transport\Http\SessionMiddleware;
use App\Infrastructure\Cache\CacheService;
use App\Infrastructure\Config\ProjectPath;
use App\Infrastructure\DI\Container;
use App\Infrastructure\Hydrator\JsonFieldAdapter;
use App\Infrastructure\Logger\Logger;

/**
 * @implements Capability<object>
 */
final readonly class SessionModule implements Capability
{
    public function register(Container $container): void
    {
        $container->set(
            SessionConfig::class,
            static function (): SessionConfig {
                /** @var array{cookie_name?: string, cookie_ttl?: int, session_ttl?: int, use_fingerprint?: bool, browser_new_session?: bool} $sessionConfig */
                $sessionConfig = require ProjectPath::getConfigPath('session.php');

                return SessionConfig::fromArray($sessionConfig);
            },
        );

        $container->set(
            ClientConfig::class,
            static function (): ClientConfig {
                /** @var array{
                 *     similarity_threshold?: float,
                 *     max_sessions_per_ip?: int,
                 *     ip_match_weight?: float,
                 *     user_agent_match_weight?: float,
                 *     attributes_match_weight?: float,
                 * } $clientConfig
                 */
                $clientConfig = require ProjectPath::getConfigPath('client.php');

                return ClientConfig::fromArray($clientConfig);
            },
        );

        $container->bind(PostgreSQLSessionRepository::class, PostgreSQLSessionRepository::class);

        $container->set(
            SessionRepository::class,
            static function (Container $container): SessionRepository {
                /** @var PostgreSQLSessionRepository $baseRepository */
                $baseRepository = $container->get(PostgreSQLSessionRepository::class);

                /** @var CacheService $cacheService */
                $cacheService = $container->get(CacheService::class);

                /** @var Logger $logger */
                $logger = $container->get(Logger::class);

                return new CachedSessionRepository($baseRepository, $cacheService, $logger);
            },
        );

        $container->set(
            SessionService::class,
            static function (Container $container): SessionService {
                /** @var SessionRepository $repository */
                $repository = $container->get(SessionRepository::class);

                /** @var Logger $logger */
                $logger = $container->get(Logger::class);

                return new SessionService($repository, $logger);
            },
        );

        $container->bind(SessionPayloadFactory::class, DefaultSessionPayloadFactory::class);
        $container->set(
            DefaultSessionPayloadFactory::class,
            static function (Container $container): DefaultSessionPayloadFactory {
                /** @var GeoLocationService $geoLocationService */
                $geoLocationService = $container->get(GeoLocationService::class);

                return new DefaultSessionPayloadFactory($geoLocationService);
            },
        );

        $container->set(
            ClientDetector::class,
            static function (Container $container): ClientDetector {
                /** @var SessionRepository $sessionRepository */
                $sessionRepository = $container->get(SessionRepository::class);

                /** @var ClientConfig $clientConfig */
                $clientConfig = $container->get(ClientConfig::class);

                return new FingerprintClientDetector($sessionRepository, $clientConfig);
            },
        );

        $container->set(
            SessionMiddleware::class,
            static function (Container $container): SessionMiddleware {
                /** @var SessionService $sessionService */
                $sessionService = $container->get(SessionService::class);

                /** @var Logger $logger */
                $logger = $container->get(Logger::class);

                /** @var SessionConfig $config */
                $config = $container->get(SessionConfig::class);

                /** @var SessionPayloadFactory $sessionPayloadFactory */
                $sessionPayloadFactory = $container->get(SessionPayloadFactory::class);

                /** @var JsonFieldAdapter $jsonAdapter */
                $jsonAdapter = $container->get(JsonFieldAdapter::class);

                /** @var ClientDetector $clientDetector */
                $clientDetector = $container->get(ClientDetector::class);

                return new SessionMiddleware(
                    $sessionService,
                    $logger,
                    $config,
                    $sessionPayloadFactory,
                    $jsonAdapter,
                    $clientDetector,
                );
            },
        );
    }
}

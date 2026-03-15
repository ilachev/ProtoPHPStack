<?php

declare(strict_types=1);

namespace App\Examples\Auth;

use App\Capabilities\Session\Domain\SessionConfig;
use App\Capabilities\Session\Domain\SessionRepository;
use App\Capabilities\Session\Domain\SessionService;
use App\Examples\Auth\Domain\AuthService;
use App\Examples\Auth\Domain\AuthUserRepository;
use App\Examples\Auth\Domain\RefreshTokenSessionRepository;
use App\Examples\Auth\Infrastructure\Persistence\PostgreSQLAuthUserRepository;
use App\Examples\Auth\Infrastructure\Persistence\PostgreSQLRefreshTokenSessionRepository;
use App\Examples\Auth\Transport\Http\AuthHandler;
use App\Examples\Auth\Transport\Http\AuthMiddleware;
use App\Examples\ExampleModule;
use App\Infrastructure\DI\Container;
use App\Infrastructure\Logger\Logger;
use App\Platform\Http\JsonResponse;

/**
 * @implements ExampleModule<object>
 */
final readonly class AuthModule implements ExampleModule
{
    public function register(Container $container): void
    {
        $container->bind(PostgreSQLAuthUserRepository::class, PostgreSQLAuthUserRepository::class);
        $container->bind(PostgreSQLRefreshTokenSessionRepository::class, PostgreSQLRefreshTokenSessionRepository::class);

        $container->set(
            AuthUserRepository::class,
            static fn(Container $container): AuthUserRepository => $container->get(PostgreSQLAuthUserRepository::class),
        );

        $container->set(
            RefreshTokenSessionRepository::class,
            static fn(Container $container): RefreshTokenSessionRepository => $container->get(PostgreSQLRefreshTokenSessionRepository::class),
        );

        $container->set(
            AuthService::class,
            static function (Container $container): AuthService {
                /** @var AuthUserRepository $userRepository */
                $userRepository = $container->get(AuthUserRepository::class);

                /** @var RefreshTokenSessionRepository $refreshTokenSessionRepository */
                $refreshTokenSessionRepository = $container->get(RefreshTokenSessionRepository::class);

                /** @var SessionRepository $sessionRepository */
                $sessionRepository = $container->get(SessionRepository::class);

                /** @var SessionService $sessionService */
                $sessionService = $container->get(SessionService::class);

                /** @var SessionConfig $sessionConfig */
                $sessionConfig = $container->get(SessionConfig::class);

                return new AuthService(
                    $userRepository,
                    $refreshTokenSessionRepository,
                    $sessionRepository,
                    $sessionService,
                    $sessionConfig,
                );
            },
        );

        $container->set(
            AuthHandler::class,
            static function (Container $container): AuthHandler {
                /** @var AuthService $authService */
                $authService = $container->get(AuthService::class);

                /** @var JsonResponse $jsonResponse */
                $jsonResponse = $container->get(JsonResponse::class);

                return new AuthHandler($authService, $jsonResponse);
            },
        );

        $container->set(
            AuthMiddleware::class,
            static function (Container $container): AuthMiddleware {
                /** @var SessionService $sessionService */
                $sessionService = $container->get(SessionService::class);

                /** @var Logger $logger */
                $logger = $container->get(Logger::class);

                return new AuthMiddleware($sessionService, $logger);
            },
        );
    }
}

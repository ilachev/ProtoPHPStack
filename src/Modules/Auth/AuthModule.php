<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Infrastructure\DI\Container;
use App\Infrastructure\Logger\Logger;
use App\Modules\Auth\Domain\AuthService;
use App\Modules\Auth\Domain\AuthUserRepository;
use App\Modules\Auth\Domain\RefreshTokenSessionRepository;
use App\Modules\Auth\Infrastructure\Persistence\PostgreSQLAuthUserRepository;
use App\Modules\Auth\Infrastructure\Persistence\PostgreSQLRefreshTokenSessionRepository;
use App\Modules\Auth\Transport\Http\AuthHandler;
use App\Modules\Auth\Transport\Http\AuthMiddleware;
use App\Modules\Module;
use App\Modules\Session\Domain\SessionConfig;
use App\Modules\Session\Domain\SessionRepository;
use App\Modules\Session\Domain\SessionService;
use App\Platform\Http\JsonResponse;

/**
 * @implements Module<object>
 */
final readonly class AuthModule implements Module
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

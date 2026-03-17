<?php

declare(strict_types=1);

namespace App\Examples\Auth;

use App\Capabilities\Session\Domain\SessionConfig;
use App\Capabilities\Session\Domain\SessionRepository;
use App\Capabilities\Session\Domain\SessionService;
use App\Examples\Auth\Domain\AuthUserRepository;
use App\Examples\Auth\Domain\EmailPasswordAuthFlow;
use App\Examples\Auth\Domain\RefreshTokenSessionRepository;
use App\Examples\Auth\Infrastructure\Persistence\SessionPayloadRefreshTokenRepository;
use App\Examples\Auth\Infrastructure\Persistence\SqlAuthUserRepository;
use App\Examples\Auth\Transport\Http\AuthHandler;
use App\Examples\ExampleModule;
use App\Platform\DI\Container;
use App\Platform\Http\JsonResponse;

/**
 * @implements ExampleModule<object>
 */
final readonly class AuthModule implements ExampleModule
{
    public function register(Container $container): void
    {
        $container->bind(SqlAuthUserRepository::class, SqlAuthUserRepository::class);
        $container->bind(SessionPayloadRefreshTokenRepository::class, SessionPayloadRefreshTokenRepository::class);

        $container->set(
            AuthUserRepository::class,
            static fn(Container $container): AuthUserRepository => $container->get(SqlAuthUserRepository::class),
        );

        $container->set(
            RefreshTokenSessionRepository::class,
            static fn(Container $container): RefreshTokenSessionRepository => $container->get(SessionPayloadRefreshTokenRepository::class),
        );

        $container->set(
            EmailPasswordAuthFlow::class,
            static function (Container $container): EmailPasswordAuthFlow {
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

                return new EmailPasswordAuthFlow(
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
                /** @var EmailPasswordAuthFlow $authFlow */
                $authFlow = $container->get(EmailPasswordAuthFlow::class);

                /** @var JsonResponse $jsonResponse */
                $jsonResponse = $container->get(JsonResponse::class);

                return new AuthHandler($authFlow, $jsonResponse);
            },
        );
    }
}

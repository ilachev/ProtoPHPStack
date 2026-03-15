<?php

declare(strict_types=1);

namespace App\Examples\Auth\Transport\Http;

use App\Api\V1\LoginRequest;
use App\Api\V1\LoginResponse;
use App\Api\V1\LogoutResponse;
use App\Api\V1\RefreshTokenRequest;
use App\Api\V1\RefreshTokenResponse;
use App\Capabilities\Session\Domain\Session;
use App\Capabilities\Session\Transport\Http\SessionResponseHeaders;
use App\Examples\Auth\Domain\AuthService;
use App\Platform\Http\Handler\AbstractJsonHandler;
use App\Platform\Http\JsonResponse;
use Google\Protobuf\Internal\Message;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class AuthHandler extends AbstractJsonHandler
{
    public function __construct(
        private AuthService $authService,
        JsonResponse $jsonResponse,
    ) {
        parent::__construct($jsonResponse);
    }

    /**
     * @throws \JsonException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return match ($request->getUri()->getPath()) {
            '/api/v1/auth/login' => $this->login($request),
            '/api/v1/auth/logout' => $this->logout($request),
            '/api/v1/auth/refresh' => $this->refresh($request),
            default => $this->jsonResponse(json_encode(['error' => 'Unsupported auth operation'], JSON_THROW_ON_ERROR), 404),
        };
    }

    /**
     * @throws \JsonException
     */
    private function login(ServerRequestInterface $request): ResponseInterface
    {
        /** @var Session|null $session */
        $session = $request->getAttribute('session');
        if ($session === null) {
            return $this->jsonResponse(json_encode(['error' => 'Session context is required'], JSON_THROW_ON_ERROR), 500);
        }

        /** @var LoginRequest|null $loginRequest */
        $loginRequest = $this->decodeMessage($request, new LoginRequest());
        if ($loginRequest === null) {
            return $this->jsonResponse(json_encode(['error' => 'Invalid login request body'], JSON_THROW_ON_ERROR), 400);
        }

        $tokens = $this->authService->login(
            $session,
            $loginRequest->getEmail(),
            $loginRequest->getPassword(),
        );

        if ($tokens === null) {
            return $this->jsonResponse(json_encode(['error' => 'Invalid credentials'], JSON_THROW_ON_ERROR), 401);
        }

        $response = (new LoginResponse())
            ->setAccessToken($tokens->accessToken)
            ->setRefreshToken($tokens->refreshToken)
            ->setExpiresIn($tokens->expiresIn);

        return $this->jsonResponse($response->serializeToJsonString());
    }

    /**
     * @throws \JsonException
     */
    private function logout(ServerRequestInterface $request): ResponseInterface
    {
        /** @var Session|null $session */
        $session = $request->getAttribute('session');
        $this->authService->logout($session);

        $response = $this->jsonResponse((new LogoutResponse())->serializeToJsonString());

        return $response->withHeader(SessionResponseHeaders::DESTROY_SESSION, '1');
    }

    /**
     * @throws \JsonException
     */
    private function refresh(ServerRequestInterface $request): ResponseInterface
    {
        /** @var RefreshTokenRequest|null $refreshRequest */
        $refreshRequest = $this->decodeMessage($request, new RefreshTokenRequest());
        if ($refreshRequest === null) {
            return $this->jsonResponse(json_encode(['error' => 'Invalid refresh request body'], JSON_THROW_ON_ERROR), 400);
        }

        /** @var Session|null $currentSession */
        $currentSession = $request->getAttribute('session');
        $tokens = $this->authService->refresh($currentSession, $refreshRequest->getRefreshToken());

        if ($tokens === null) {
            return $this->jsonResponse(json_encode(['error' => 'Invalid refresh token'], JSON_THROW_ON_ERROR), 401);
        }

        $response = (new RefreshTokenResponse())
            ->setAccessToken($tokens->accessToken)
            ->setRefreshToken($tokens->refreshToken)
            ->setExpiresIn($tokens->expiresIn);

        return $this->jsonResponse($response->serializeToJsonString())
            ->withHeader(SessionResponseHeaders::ACTIVE_SESSION_ID, $tokens->accessToken);
    }

    /**
     * @template T of Message
     * @param T $message
     * @return T|null
     */
    private function decodeMessage(ServerRequestInterface $request, Message $message): ?Message
    {
        $body = (string) $request->getBody();
        if ($body === '') {
            return null;
        }

        try {
            $message->mergeFromJsonString($body);

            return $message;
        } catch (\Throwable) {
            return null;
        }
    }
}

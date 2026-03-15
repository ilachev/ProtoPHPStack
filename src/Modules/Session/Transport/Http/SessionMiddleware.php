<?php

declare(strict_types=1);

namespace App\Modules\Session\Transport\Http;

use App\Infrastructure\Hydrator\JsonFieldAdapter;
use App\Infrastructure\Logger\Logger;
use App\Modules\Session\Application\ClientDetector;
use App\Modules\Session\Application\SessionPayloadFactory;
use App\Modules\Session\Domain\Session;
use App\Modules\Session\Domain\SessionConfig;
use App\Modules\Session\Domain\SessionService;
use App\Platform\Http\Middleware;
use App\Platform\Http\RequestHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class SessionMiddleware implements Middleware
{
    public function __construct(
        private SessionService $sessionService,
        private Logger $logger,
        private SessionConfig $config,
        private SessionPayloadFactory $sessionPayloadFactory,
        private JsonFieldAdapter $jsonAdapter,
        private ClientDetector $clientDetector,
    ) {}

    public function getContext(string $name): mixed
    {
        return match ($name) {
            'sessionService' => $this->sessionService,
            'logger' => $this->logger,
            'config' => $this->config,
            'sessionPayloadFactory' => $this->sessionPayloadFactory,
            'jsonAdapter' => $this->jsonAdapter,
            'clientDetector' => $this->clientDetector,
            default => null,
        };
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandler $handler,
    ): ResponseInterface {
        $sessionId = $this->extractSessionId($request);
        $session = null;
        $hadSessionIdButInvalid = false;

        if ($sessionId !== null) {
            $session = $this->sessionService->validateSession($sessionId);
            if ($session === null) {
                $hadSessionIdButInvalid = true;
            }
        }

        $sessionPayload = $this->sessionPayloadFactory->createFromRequest($request);
        $payload = $this->jsonAdapter->serialize($sessionPayload);
        $isBrowser = $sessionPayload->isBrowser();

        if ($session === null) {
            if (($isBrowser && $this->config->browserNewSession) || $hadSessionIdButInvalid) {
                $session = $this->sessionService->createSession(
                    userId: null,
                    payload: $payload,
                    ttl: $this->config->sessionTtl,
                );

                $logContext = [
                    'session_id' => $session->id,
                    'user_agent' => $sessionPayload->userAgent,
                ];

                if ($hadSessionIdButInvalid) {
                    $this->logger->info('Invalid session ID detected, created new session', [
                        ...$logContext,
                        'invalid_session_id' => $sessionId,
                    ]);
                } else {
                    $this->logger->debug('Created new browser session', $logContext);
                }
            } elseif ($this->config->useFingerprint) {
                $tempSession = $this->sessionService->createSession(
                    userId: null,
                    payload: $payload,
                    ttl: 60,
                );

                $requestWithTempSession = $request->withAttribute('session', $tempSession);
                $similarClients = $this->clientDetector->findSimilarClients($requestWithTempSession);

                if (!empty($similarClients)) {
                    $bestMatch = $similarClients[0];
                    $matchedSession = $this->sessionService->validateSession($bestMatch->id);

                    if ($matchedSession !== null) {
                        $session = $matchedSession;
                        $this->logger->debug('Restored session by fingerprint', [
                            'session_id' => $session->id,
                            'client_ip' => $sessionPayload->ip,
                            'user_agent' => $sessionPayload->userAgent,
                        ]);

                        $this->sessionService->deleteSession($tempSession->id);
                    } else {
                        $session = $tempSession;
                    }
                } else {
                    $session = $tempSession;
                    $this->logger->debug('Created new session, no similar clients found', [
                        'session_id' => $session->id,
                    ]);
                }
            } else {
                $session = $this->sessionService->createSession(
                    userId: null,
                    payload: $payload,
                    ttl: $this->config->sessionTtl,
                );

                $this->logger->debug('Created new session', ['session_id' => $session->id]);
            }
        }

        $request = $request->withAttribute('session', $session);
        $response = $handler->handle($request);

        if ($response->getStatusCode() < 400) {
            $this->sessionService->refreshSession($session->id, $this->config->sessionTtl);
            $response = $this->addSessionCookie($response, $session);
        }

        return $response;
    }

    private function extractSessionId(ServerRequestInterface $request): ?string
    {
        $authHeader = $request->getHeaderLine('Authorization');
        if (str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        $cookies = $request->getCookieParams();
        if (!isset($cookies[$this->config->cookieName])) {
            return null;
        }

        $cookie = $cookies[$this->config->cookieName];

        return \is_string($cookie) ? $cookie : null;
    }

    private function addSessionCookie(ResponseInterface $response, Session $session): ResponseInterface
    {
        $expires = gmdate('D, d M Y H:i:s T', $session->expiresAt);

        return $response->withAddedHeader(
            'Set-Cookie',
            \sprintf(
                '%s=%s; Expires=%s; Path=/; HttpOnly; SameSite=Lax',
                $this->config->cookieName,
                $session->id,
                $expires,
            ),
        );
    }
}

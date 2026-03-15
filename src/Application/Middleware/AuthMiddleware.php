<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Infrastructure\Logger\Logger;
use App\Modules\Session\Domain\Session;
use App\Modules\Session\Domain\SessionService;
use App\Platform\Http\Middleware;
use App\Platform\Http\RequestHandler;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class AuthMiddleware implements Middleware
{
    // Paths that require an authenticated user.
    private const array PROTECTED_PATHS = [
        '/api/v1/user/',
        '/api/v1/admin/',
    ];
    private const string COOKIE_NAME = 'session';
    private const int COOKIE_TTL = 86400; // 24 hours

    public function __construct(
        private SessionService $sessionService,
        private Logger $logger,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandler $handler,
    ): ResponseInterface {
        // Try to resolve an existing session first.
        $sessionId = $this->extractSessionId($request);
        $session = null;

        if ($sessionId !== null) {
            $session = $this->sessionService->validateSession($sessionId);
        }

        // Create an anonymous session when the request has no valid session.
        if ($session === null) {
            // Protected paths require an existing authenticated session.
            $path = $request->getUri()->getPath();

            foreach (self::PROTECTED_PATHS as $protectedPath) {
                if (str_starts_with($path, $protectedPath)) {
                    $jsonBody = json_encode(['error' => 'Unauthorized access']);

                    return new Response(
                        401,
                        ['Content-Type' => 'application/json'],
                        $jsonBody !== false ? $jsonBody : '{"error":"JSON encoding failed"}',
                    );
                }
            }

            // Public paths receive an anonymous session automatically.
            $payload = json_encode(['ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown']);
            if ($payload === false) {
                $payload = '{}';
            }

            $session = $this->sessionService->createSession(
                userId: null,
                payload: $payload,
                ttl: self::COOKIE_TTL,
            );

            $this->logger->info('Created anonymous session', ['session_id' => $session->id]);
        }

        // Attach session data to request attributes for downstream handlers.
        $request = $request->withAttribute('session', $session);

        if ($session->userId !== null) {
            $request = $request->withAttribute('userId', $session->userId);
            $request = $request->withAttribute('isAuthenticated', true);
        } else {
            $request = $request->withAttribute('isAuthenticated', false);
        }

        // Delegate request processing to the next handler.
        $response = $handler->handle($request);

        // Refresh session metadata and propagate the session cookie.
        if ($response->getStatusCode() < 400) {
            $session = $this->sessionService->refreshSession($session->id);

            // Attach the refreshed session cookie.
            if ($session !== null) {
                $response = $this->addSessionCookie($response, $session);
            }
        }

        return $response;
    }

    /**
     * Extract session ID from the request.
     */
    private function extractSessionId(ServerRequestInterface $request): ?string
    {
        // Prefer bearer token authentication when present.
        $authHeader = $request->getHeaderLine('Authorization');
        if (str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // Fallback to cookie-based session lookup.
        $cookies = $request->getCookieParams();

        $cookieValue = $cookies[self::COOKIE_NAME] ?? null;

        return \is_string($cookieValue) ? $cookieValue : null;
    }

    private function addSessionCookie(ResponseInterface $response, Session $session): ResponseInterface
    {
        $expires = gmdate('D, d M Y H:i:s T', $session->expiresAt);

        return $response->withAddedHeader(
            'Set-Cookie',
            \sprintf(
                '%s=%s; Expires=%s; Path=/; HttpOnly; SameSite=Lax',
                self::COOKIE_NAME,
                $session->id,
                $expires,
            ),
        );
    }
}

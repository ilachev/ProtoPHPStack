<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Middleware;

use App\Application\Client\ClientDetector;
use App\Application\Client\ClientIdentity;
use App\Application\Client\SessionPayloadFactory;
use App\Application\Http\RequestHandler;
use App\Application\Middleware\SessionMiddleware;
use App\Domain\Session\Session;
use App\Domain\Session\SessionConfig;
use App\Domain\Session\SessionPayload;
use App\Domain\Session\SessionRepository;
use App\Domain\Session\SessionService;
use App\Infrastructure\Hydrator\JsonFieldAdapter;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Tests\Unit\Infrastructure\Logger\TestLogger;

final class SessionMiddlewareTest extends TestCase
{
    /**
     * Creates a SessionPayload object for tests.
     */
    private function createTestSessionPayload(
        ?string $userAgent = null,
        string $ip = '127.0.0.1',
        ?string $acceptLanguage = 'en-US',
        ?string $acceptEncoding = 'gzip',
        ?string $xForwardedFor = null,
    ): SessionPayload {
        return new SessionPayload(
            ip: $ip,
            userAgent: $userAgent,
            acceptLanguage: $acceptLanguage,
            acceptEncoding: $acceptEncoding,
            xForwardedFor: $xForwardedFor,
            referer: null,
            origin: null,
            secChUa: null,
            secChUaPlatform: null,
            secChUaMobile: null,
            dnt: null,
            secFetchDest: null,
            secFetchMode: null,
            secFetchSite: null,
        );
    }

    private TestSessionRepository $repository;

    private SessionService $sessionService;

    private TestLogger $logger;

    private SessionMiddleware $middleware;

    private TestRequestHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new TestSessionRepository();
        $this->logger = new TestLogger();
        $this->sessionService = new SessionService($this->repository, $this->logger);

        $config = SessionConfig::fromArray([
            'cookie_name' => 'session',
            'cookie_ttl' => 86400,
            'session_ttl' => 3600,
            'use_fingerprint' => false,
        ]);

        // Create test session payload
        $sessionPayload = $this->createTestSessionPayload('Test Agent');

        // Use concrete implementations for testing
        $sessionPayloadFactory = new TestSessionPayloadFactoryImpl($sessionPayload);
        $jsonAdapter = new TestJsonFieldAdapterImpl();

        // Создаем тестовую реализацию ClientDetector
        $clientDetector = new TestClientDetectorImpl([]);

        $this->middleware = new SessionMiddleware(
            $this->sessionService,
            $this->logger,
            $config,
            $sessionPayloadFactory,
            $jsonAdapter,
            $clientDetector,
        );
        $this->handler = new TestRequestHandler();
    }

    public function testCreatesNewSessionWhenNoSessionIdInRequest(): void
    {
        $request = new ServerRequest('GET', '/');
        $this->handler->response = new Response();

        $response = $this->middleware->process($request, $this->handler);

        // Проверяем, что была создана новая сессия
        self::assertCount(1, $this->repository->sessions);

        // Проверяем, что в атрибутах запроса есть сессия
        $processedRequest = $this->handler->lastRequest;
        self::assertNotNull($processedRequest);

        $session = $processedRequest->getAttribute('session');
        self::assertInstanceOf(Session::class, $session);
        self::assertNull($session->userId);

        // Проверяем логирование создания сессии
        self::assertGreaterThanOrEqual(1, \count($this->logger->logs));

        $sessionCreatedMessage = false;
        foreach ($this->logger->logs as $log) {
            if (strpos($log['message'], 'Created new session') !== false) {
                $sessionCreatedMessage = true;
                break;
            }
        }

        self::assertTrue($sessionCreatedMessage, 'Log should contain "Created new session" message');

        // Проверяем cookie в ответе
        self::assertTrue($response->hasHeader('Set-Cookie'));
        $cookie = $response->getHeaderLine('Set-Cookie');
        self::assertStringContainsString('session=', $cookie);
        self::assertStringContainsString('HttpOnly', $cookie);
    }

    public function testUsesExistingSessionFromCookie(): void
    {
        // Создаем сессию в репозитории
        $existingSession = new Session(
            id: 'existing-session-id',
            userId: 1,
            payload: '{}',
            expiresAt: time() + 3600,
            createdAt: time() - 100,
            updatedAt: time() - 50,
        );
        $this->repository->sessions['existing-session-id'] = $existingSession;

        // Создаем запрос с cookie
        $request = new ServerRequest('GET', '/');
        $request = $request->withCookieParams(['session' => 'existing-session-id']);

        $this->handler->response = new Response();

        $response = $this->middleware->process($request, $this->handler);

        // Проверяем, что существующая сессия получена и не создана новая
        self::assertCount(1, $this->repository->sessions);

        // Проверяем атрибуты запроса
        $processedRequest = $this->handler->lastRequest;
        self::assertNotNull($processedRequest);

        $session = $processedRequest->getAttribute('session');
        self::assertSame($existingSession, $session);

        // Проверяем cookie в ответе
        self::assertTrue($response->hasHeader('Set-Cookie'));
        $cookie = $response->getHeaderLine('Set-Cookie');
        self::assertStringContainsString('session=existing-session-id', $cookie);
    }

    public function testUsesExistingSessionFromBearerToken(): void
    {
        // Создаем сессию в репозитории
        $existingSession = new Session(
            id: 'token-session-id',
            userId: 1,
            payload: '{}',
            expiresAt: time() + 3600,
            createdAt: time() - 100,
            updatedAt: time() - 50,
        );
        $this->repository->sessions['token-session-id'] = $existingSession;

        // Создаем запрос с заголовком Authorization
        $request = new ServerRequest('GET', '/');
        $request = $request->withHeader('Authorization', 'Bearer token-session-id');

        $this->handler->response = new Response();

        $response = $this->middleware->process($request, $this->handler);

        // Проверяем, что существующая сессия получена и не создана новая
        self::assertCount(1, $this->repository->sessions);

        // Проверяем атрибуты запроса
        $processedRequest = $this->handler->lastRequest;
        self::assertNotNull($processedRequest);

        $session = $processedRequest->getAttribute('session');
        self::assertSame($existingSession, $session);

        // Проверяем cookie в ответе
        self::assertTrue($response->hasHeader('Set-Cookie'));
        $cookie = $response->getHeaderLine('Set-Cookie');
        self::assertStringContainsString('session=token-session-id', $cookie);
    }

    public function testCreatesNewSessionWhenExistingSessionIsInvalid(): void
    {
        // Создаем просроченную сессию в репозитории
        $expiredSession = new Session(
            id: 'invalid-session-id',
            userId: 1,
            payload: '{}',
            expiresAt: time() - 100, // В прошлом
            createdAt: time() - 200,
            updatedAt: time() - 100,
        );
        $this->repository->sessions['invalid-session-id'] = $expiredSession;

        // Создаем запрос с cookie
        $request = new ServerRequest('GET', '/');
        $request = $request->withCookieParams(['session' => 'invalid-session-id']);

        $this->handler->response = new Response();

        $response = $this->middleware->process($request, $this->handler);

        // Проверяем, что была создана новая сессия
        self::assertCount(2, $this->repository->sessions); // Просроченная + новая

        // Проверяем атрибуты запроса
        $processedRequest = $this->handler->lastRequest;
        self::assertNotNull($processedRequest);

        $session = $processedRequest->getAttribute('session');
        self::assertInstanceOf(Session::class, $session);
        self::assertNotSame($expiredSession, $session);
        self::assertNull($session->userId);

        // Проверяем cookie в ответе
        self::assertTrue($response->hasHeader('Set-Cookie'));
        $cookie = $response->getHeaderLine('Set-Cookie');
        self::assertStringContainsString('session=', $cookie);
        self::assertStringNotContainsString('session=invalid-session-id', $cookie);
    }

    public function testDoesNotRefreshSessionForErrorResponses(): void
    {
        $request = new ServerRequest('GET', '/');
        $this->handler->response = new Response(500);

        $response = $this->middleware->process($request, $this->handler);

        // Проверяем, что была создана сессия
        self::assertCount(1, $this->repository->sessions);

        // Проверяем, что в ответе нет cookie
        self::assertFalse($response->hasHeader('Set-Cookie'));
    }

    public function testReusesSimilarSessionWhenFingerprintMatches(): void
    {
        // 1. Включаем fingerprinting в конфиге
        $configWithFingerprint = SessionConfig::fromArray([
            'cookie_name' => 'session',
            'cookie_ttl' => 86400,
            'session_ttl' => 3600,
            'use_fingerprint' => true,
        ]);

        // 2. Создаем существующую сессию с известным fingerprint
        $existingSession = new Session(
            id: 'existing-fingerprint-session-id',
            userId: 42,
            payload: '{"ip":"127.0.0.1","userAgent":"Test Agent"}',
            expiresAt: time() + 3600,
            createdAt: time() - 100,
            updatedAt: time() - 50,
        );
        $this->repository->sessions['existing-fingerprint-session-id'] = $existingSession;

        // 3. Создаем ClientIdentity на основе существующей сессии
        $existingIdentity = new ClientIdentity(
            id: 'existing-fingerprint-session-id',
            ipAddress: '127.0.0.1',
            userAgent: 'Test Agent',
        );

        // 4. Создаем тестовую реализацию ClientDetector, которая будет возвращать наше совпадение
        $clientDetector = new TestClientDetectorImpl([$existingIdentity]);

        // 5. Создаем новый middleware с этими зависимостями
        /** @var SessionPayloadFactory $sessionPayloadFactory */
        $sessionPayloadFactory = $this->middleware->getContext('sessionPayloadFactory');

        /** @var JsonFieldAdapter $jsonAdapter */
        $jsonAdapter = $this->middleware->getContext('jsonAdapter');

        $fingerprintMiddleware = new SessionMiddleware(
            $this->sessionService,
            $this->logger,
            $configWithFingerprint,
            $sessionPayloadFactory,
            $jsonAdapter,
            $clientDetector,
        );

        // 6. Создаем запрос без cookie, но с совпадающим fingerprint
        $request = new ServerRequest('GET', '/');
        $this->handler->response = new Response();

        // 7. Запускаем middleware
        $response = $fingerprintMiddleware->process($request, $this->handler);

        // 8. Проверяем, что не была создана новая сессия
        self::assertCount(1, $this->repository->sessions);

        // 9. Проверяем, что в атрибутах запроса используется существующая сессия
        $processedRequest = $this->handler->lastRequest;
        self::assertNotNull($processedRequest);

        $session = $processedRequest->getAttribute('session');
        self::assertInstanceOf(Session::class, $session);
        self::assertSame($existingSession, $session);
        self::assertSame(42, $session->userId);

        // 10. Проверяем cookie в ответе
        self::assertTrue($response->hasHeader('Set-Cookie'));
        $cookie = $response->getHeaderLine('Set-Cookie');
        self::assertStringContainsString('session=existing-fingerprint-session-id', $cookie);
    }
}

final class TestRequestHandler implements RequestHandler
{
    public ?ServerRequestInterface $lastRequest = null;

    public ResponseInterface $response;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->lastRequest = $request;

        return $this->response;
    }
}


/**
 * Тестовый репозиторий для тестирования SessionMiddleware.
 */
final class TestSessionRepository implements SessionRepository
{
    /** @var array<string, Session> */
    public array $sessions = [];

    public function findById(string $id): ?Session
    {
        return $this->sessions[$id] ?? null;
    }

    /**
     * @return array<Session>
     */
    public function findByUserId(int $userId): array
    {
        return array_filter(
            $this->sessions,
            static fn(Session $session) => $session->userId === $userId,
        );
    }

    /**
     * @return array<Session>
     */
    public function findAll(): array
    {
        return array_values($this->sessions);
    }

    public function save(Session $session): void
    {
        $this->sessions[$session->id] = $session;
    }

    public function delete(string $id): void
    {
        unset($this->sessions[$id]);
    }

    public function deleteExpired(): void
    {
        $now = time();

        foreach ($this->sessions as $id => $session) {
            if ($session->expiresAt < $now) {
                unset($this->sessions[$id]);
            }
        }
    }
}

/**
 * Test implementation of SessionPayloadFactory.
 */
final readonly class TestSessionPayloadFactoryImpl implements SessionPayloadFactory
{
    public function __construct(
        private SessionPayload $sessionPayload,
    ) {}

    public function createFromRequest(ServerRequestInterface $request): SessionPayload
    {
        return $this->sessionPayload;
    }

    public function createDefault(): SessionPayload
    {
        return $this->sessionPayload;
    }
}

/**
 * Тестовая имплементация JsonFieldAdapter.
 */
final readonly class TestJsonFieldAdapterImpl implements JsonFieldAdapter
{
    public function serialize(object $object, ?callable $fieldTransformer = null): string
    {
        return '{"ip":"127.0.0.1","userAgent":"Test Agent"}';
    }

    /**
     * Десериализует JSON в объект указанного класса.
     *
     * @param string $jsonValue JSON для десериализации
     * @param string $targetClass Имя класса, в который нужно десериализовать
     * @param callable|null $fieldTransformer Опциональный трансформер полей
     * @return object Результат десериализации
     */
    public function deserialize(string $jsonValue, string $targetClass, ?callable $fieldTransformer = null): object
    {
        // Always return a SessionPayload object for tests
        $result = new SessionPayload(
            ip: '127.0.0.1',
            userAgent: 'Test Agent',
            acceptLanguage: 'en-US',
            acceptEncoding: 'gzip',
            xForwardedFor: null,
            referer: null,
            origin: null,
            secChUa: null,
            secChUaPlatform: null,
            secChUaMobile: null,
            dnt: null,
            secFetchDest: null,
            secFetchMode: null,
            secFetchSite: null,
        );

        return $result;
    }

    /**
     * Безопасно десериализует JSON.
     */
    public function tryDeserialize(string $jsonValue, string $targetClass, object $defaultValue, ?callable $fieldTransformer = null): object
    {
        try {
            return $this->deserialize($jsonValue, $targetClass, $fieldTransformer);
        } catch (\Throwable) {
            return $defaultValue;
        }
    }

    public function trySerialize(object $object, string $defaultJson = '{}', ?callable $fieldTransformer = null): string
    {
        return $this->serialize($object, $fieldTransformer);
    }
}

/**
 * Тестовая имплементация ClientDetector.
 */
final readonly class TestClientDetectorImpl implements ClientDetector
{
    /**
     * @param array<ClientIdentity> $similarClients Клиенты, которые будут возвращены
     */
    public function __construct(
        private array $similarClients = [],
    ) {}

    /**
     * @return array<ClientIdentity>
     */
    public function findSimilarClients(ServerRequestInterface $request, bool $includeCurrent = false): array
    {
        return $this->similarClients;
    }

    public function isRequestSuspicious(ServerRequestInterface $request): bool
    {
        return false;
    }
}

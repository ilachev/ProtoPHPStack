<?php

declare(strict_types=1);

namespace Tests\Unit\Capabilities\Session\Infrastructure\Persistence;

use App\Capabilities\Session\Domain\Session;
use App\Capabilities\Session\Domain\SessionRepository;
use App\Capabilities\Session\Infrastructure\Persistence\CachedSessionRepository;
use App\Platform\Cache\CacheConfig;
use App\Platform\Cache\CacheService;
use App\Platform\Cache\RoadRunnerCacheService;
use App\Platform\Cache\ScopedCacheFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Platform\Cache\MockStorage;
use Tests\Unit\Platform\Logging\TestLogger;

final class CachedSessionRepositoryTest extends TestCase
{
    private const SESSION_ID = '1234567890abcdef1234567890abcdef';
    private const USER_ID = 123;

    private CachedSessionRepository $repository;

    private MockObject&SessionRepository $innerRepository;

    private CacheService $cacheService;

    private MockStorage $storage;

    protected function setUp(): void
    {
        // Mock the inner repository.
        $this->innerRepository = $this->createMock(SessionRepository::class);

        // Create test logger.
        $logger = new TestLogger();

        // Use in-memory storage instead of the RoadRunner backend.
        $this->storage = new MockStorage();

        $cacheConfig = new CacheConfig(
            engine: 'mock',
            address: 'tcp://127.0.0.1:6001',
            defaultPrefix: 'test:',
            namespaceSeed: '',
            defaultTtl: 3600,
        );

        // Create cache service without RoadRunner dependencies.
        $this->cacheService = new RoadRunnerCacheService($cacheConfig, $logger);

        // Replace the backend storage with the test double.
        $reflection = new \ReflectionProperty($this->cacheService, 'storage');
        $reflection->setValue($this->cacheService, $this->storage);

        // Force the cache service into available mode.
        $reflection = new \ReflectionProperty($this->cacheService, 'available');
        $reflection->setValue($this->cacheService, true);

        // Create repository under test.
        $this->repository = new CachedSessionRepository(
            $this->innerRepository,
            new ScopedCacheFactory($this->cacheService),
            $logger,
        );
    }

    public function testFindByIdUsesCache(): void
    {
        $session = $this->createSession();

        $this->innerRepository
            ->expects(self::once())
            ->method('findById')
            ->with(self::SESSION_ID)
            ->willReturn($session);

        $result1 = $this->repository->findById(self::SESSION_ID);
        self::assertSame($session, $result1);

        self::assertTrue($this->storage->has($this->storageKey('session:' . self::SESSION_ID)));

        $result2 = $this->repository->findById(self::SESSION_ID);
        self::assertSame($session, $result2);
    }

    public function testFindByUserIdUsesCache(): void
    {
        $sessions = [$this->createSession()];

        $this->innerRepository
            ->expects(self::once())
            ->method('findByUserId')
            ->with(self::USER_ID)
            ->willReturn($sessions);

        $result1 = $this->repository->findByUserId(self::USER_ID);
        self::assertSame($sessions, $result1);

        self::assertTrue($this->storage->has($this->storageKey('session_user:' . self::USER_ID)));

        $result2 = $this->repository->findByUserId(self::USER_ID);
        self::assertSame($sessions, $result2);
    }

    public function testFindAllDoesNotUseCache(): void
    {
        $sessions = [$this->createSession()];

        $this->innerRepository
            ->expects(self::exactly(2))
            ->method('findAll')
            ->willReturn($sessions);

        $result1 = $this->repository->findAll();
        self::assertSame($sessions, $result1);

        $result2 = $this->repository->findAll();
        self::assertSame($sessions, $result2);
    }

    public function testSaveUpdatesCache(): void
    {
        $session = $this->createSession();

        $this->innerRepository
            ->expects(self::once())
            ->method('save')
            ->with($session)
            ->willReturn($session);

        $savedSession = $this->repository->save($session);

        self::assertTrue($this->storage->has($this->storageKey('session:' . self::SESSION_ID)));
        self::assertSame($session, $savedSession);

        $cachedSession = $this->cacheService->get('session:' . self::SESSION_ID);
        self::assertSame($session, $cachedSession);
    }

    public function testDeleteInvalidatesCache(): void
    {
        $session = $this->createSession();

        $this->innerRepository
            ->expects(self::once())
            ->method('findById')
            ->with(self::SESSION_ID)
            ->willReturn($session);

        $this->innerRepository
            ->expects(self::once())
            ->method('delete')
            ->with(self::SESSION_ID);

        $this->cacheService->set('session:' . self::SESSION_ID, $session);
        $this->cacheService->set('session_user:' . self::USER_ID, [$session]);

        $this->repository->delete(self::SESSION_ID);

        self::assertFalse($this->storage->has($this->storageKey('session:' . self::SESSION_ID)));
        self::assertFalse($this->storage->has($this->storageKey('session_user:' . self::USER_ID)));
    }

    public function testDeleteExpiredDoesNotInvalidateCache(): void
    {
        $session = $this->createSession();

        $this->cacheService->set('session:' . self::SESSION_ID, $session);

        $this->innerRepository
            ->expects(self::once())
            ->method('deleteExpired');

        $this->repository->deleteExpired();

        self::assertTrue($this->storage->has($this->storageKey('session:' . self::SESSION_ID)));
    }

    public function testFindByIdCachesNullResult(): void
    {
        $this->innerRepository
            ->expects(self::once())
            ->method('findById')
            ->with(self::SESSION_ID)
            ->willReturn(null);

        self::assertNull($this->repository->findById(self::SESSION_ID));
        self::assertTrue($this->storage->has($this->storageKey('session:' . self::SESSION_ID)));
        self::assertNull($this->repository->findById(self::SESSION_ID));
    }

    private function createSession(string $payload = '{"foo":"bar"}'): Session
    {
        $now = time();
        $expiresAt = $now + 3600;

        return new Session(
            id: self::SESSION_ID,
            userId: self::USER_ID,
            payload: $payload,
            expiresAt: $expiresAt,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    private function storageKey(string $key): string
    {
        return 'test:1:' . $key;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Cache;

use App\Platform\Cache\CacheConfig;
use App\Platform\Cache\CacheScope;
use App\Platform\Cache\CacheService;
use App\Platform\Cache\RoadRunnerCacheService;
use App\Platform\Logging\Logger;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Platform\Logging\TestLogger;

final class RoadRunnerCacheServiceTest extends TestCase
{
    private CacheConfig $config;

    private Logger $logger;

    private CacheService $cacheService;

    private MockStorage $mockStorage;

    protected function setUp(): void
    {
        // Create test configuration.
        $this->config = new CacheConfig(
            engine: 'memory',
            address: 'tcp://localhost:6001',
            defaultPrefix: 'test:',
            namespaceSeed: 'deploy-42',
            defaultTtl: 60,
        );

        // Create logger.
        $this->logger = new TestLogger();

        // Create in-memory storage.
        $this->mockStorage = new MockStorage();

        // Create cache service.
        $this->cacheService = new RoadRunnerCacheService(
            $this->config,
            $this->logger,
        );

        // Override the backend storage with the test double.
        $reflectionProperty = new \ReflectionProperty(RoadRunnerCacheService::class, 'storage');
        $reflectionProperty->setValue($this->cacheService, $this->mockStorage);
    }

    public function testSetAndGet(): void
    {
        // Store value.
        $key = 'test-key';
        $value = ['foo' => 'bar'];

        self::assertTrue($this->cacheService->set($key, $value));

        // Read value.
        $retrieved = $this->cacheService->get($key);
        self::assertSame($value, $retrieved);
    }

    public function testUsesDeploymentNamespaceSeedInPrefixedKeys(): void
    {
        self::assertTrue($this->cacheService->set('namespaced-key', 'value'));

        self::assertTrue($this->mockStorage->has('test:deploy-42:1:namespaced-key'));
    }

    public function testAcceptsTypedCacheKeys(): void
    {
        $cacheKey = (new CacheScope('typed'))->key('identifier');

        self::assertTrue($this->cacheService->set($cacheKey, 'typed-value'));
        self::assertTrue($this->cacheService->has($cacheKey));
        self::assertSame('typed-value', $this->cacheService->get($cacheKey));
        self::assertTrue($this->mockStorage->has('test:deploy-42:1:typed:identifier'));
    }

    public function testGetNonExistentKey(): void
    {
        $key = 'non-existent';
        $default = 'default-value';

        $value = $this->cacheService->get($key, $default);
        self::assertSame($default, $value);
    }

    public function testHas(): void
    {
        $key = 'existing-key';
        $value = 'test-value';

        // Assert missing key before write.
        self::assertFalse($this->cacheService->has($key));

        // Store value.
        $this->cacheService->set($key, $value);

        // Assert key exists after write.
        self::assertTrue($this->cacheService->has($key));
    }

    public function testDelete(): void
    {
        $key = 'to-delete';
        $value = 'delete-me';

        // Store value.
        $this->cacheService->set($key, $value);
        self::assertTrue($this->cacheService->has($key));

        // Delete value.
        self::assertTrue($this->cacheService->delete($key));
        self::assertFalse($this->cacheService->has($key));
    }

    public function testClear(): void
    {
        // Store multiple values.
        $this->cacheService->set('key1', 'value1');
        $this->cacheService->set('key2', 'value2');

        self::assertTrue($this->cacheService->has('key1'));
        self::assertTrue($this->cacheService->has('key2'));

        // Clear cache.
        self::assertTrue($this->cacheService->clear());

        self::assertFalse($this->cacheService->has('key1'));
        self::assertFalse($this->cacheService->has('key2'));
    }

    public function testClearRotatesNamespaceInsteadOfWipingBackend(): void
    {
        self::assertTrue($this->cacheService->set('rotating-key', 'value-before-clear'));
        self::assertSame('value-before-clear', $this->mockStorage->get('test:deploy-42:1:rotating-key'));

        self::assertTrue($this->cacheService->clear());
        self::assertFalse($this->cacheService->has('rotating-key'));
        self::assertSame('value-before-clear', $this->mockStorage->get('test:deploy-42:1:rotating-key'));

        $namespaceVersion = $this->mockStorage->get('test:deploy-42:__namespace_version');
        self::assertIsString($namespaceVersion);
        self::assertNotSame('1', $namespaceVersion);

        self::assertTrue($this->cacheService->set('rotating-key', 'value-after-clear'));
        self::assertSame(
            'value-after-clear',
            $this->mockStorage->get("test:deploy-42:{$namespaceVersion}:rotating-key"),
        );
    }

    public function testGetOrSet(): void
    {
        $key = 'computed-value';
        $computeCount = 0;

        $callback = static function () use (&$computeCount) {
            ++$computeCount;

            return 'computed-result';
        };

        // First call should compute and store the value.
        $result1 = $this->cacheService->getOrSet($key, $callback);
        self::assertSame('computed-result', $result1);
        self::assertSame(1, $computeCount, 'Callback must be called exactly once');

        // Second call should reuse the cached value.
        $result2 = $this->cacheService->getOrSet($key, $callback);
        self::assertSame('computed-result', $result2);
        // The callback must not run again once the value is cached.
        self::assertSame(1, $computeCount, 'Callback must not be called again');
    }

    public function testGetOrSetCachesNullValues(): void
    {
        $key = 'nullable-value';
        $computeCount = 0;

        $callback = static function () use (&$computeCount) {
            ++$computeCount;

            return null;
        };

        $this->cacheService->getOrSet($key, $callback);
        $this->cacheService->getOrSet($key, $callback);

        self::assertTrue($this->cacheService->has($key));
        self::assertNull($this->cacheService->get($key));
        self::assertSame(1, $computeCount, 'Null values must be cached as a real hit');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Hydration;

use App\Platform\Hydration\LimitedReflectionCache;
use App\Platform\Hydration\ProtobufHydration;
use App\Platform\Hydration\SetterProtobufHydration;
use Google\Protobuf\Internal\Message;
use PHPUnit\Framework\TestCase;

final class CacheLimitTest extends TestCase
{
    /**
     * Verify that the cache has a size limiting property.
     */
    public function testReflectionCacheLimitDefinition(): void
    {
        $cache = new LimitedReflectionCache();
        $reflection = new \ReflectionClass(LimitedReflectionCache::class);
        $property = $reflection->getProperty('maxCacheSize');
        $maxCacheSize = $property->getValue($cache);

        self::assertIsInt($maxCacheSize);
        self::assertGreaterThan(10, $maxCacheSize, 'Cache size should be reasonably large');
        self::assertLessThan(1000, $maxCacheSize, 'Cache size should be bounded');
    }

    /**
     * Verifies that the protobuf hydration strategy is available.
     */
    public function testProtobufHydrationExists(): void
    {
        self::assertTrue(interface_exists(ProtobufHydration::class));
        self::assertTrue(class_exists(SetterProtobufHydration::class));

        $implementation = new SetterProtobufHydration();
        self::assertInstanceOf(ProtobufHydration::class, $implementation);
    }

    /**
     * Verifies that LimitedReflectionCache has cache limiting mechanism.
     */
    public function testCacheHasCacheLimitingMechanism(): void
    {
        // Verify the cache class has cache limiting code
        $filename = (new \ReflectionClass(LimitedReflectionCache::class))->getFileName();
        self::assertNotFalse($filename);
        $source = file_get_contents((string) $filename);
        self::assertNotFalse($source);

        // Using simple string checking to verify cache limiting code exists
        self::assertStringContainsString('reset($cache)', $source);
        self::assertStringContainsString('key($cache)', $source);
        self::assertStringContainsString('unset($cache[$firstKey])', $source);

        // Verify the specific limiting logic is present
        self::assertStringContainsString('if (\count($cache) >= $this->maxCacheSize)', $source);
    }

    /**
     * This test directly checks the cache limiting logic in LimitedReflectionCache.
     */
    public function testCacheLimitingLogic(): void
    {
        $reflection = new \ReflectionClass(LimitedReflectionCache::class);

        // Extract the manageCache method
        $manageCacheMethod = $reflection->getMethod('manageCache');
        $manageCacheStartLine = $manageCacheMethod->getStartLine();
        $manageCacheEndLine = $manageCacheMethod->getEndLine();

        $filename = (string) $reflection->getFileName();
        self::assertFileExists($filename);
        $sourceCode = file_get_contents($filename);
        self::assertNotFalse($sourceCode);

        $sourceLines = explode("\n", $sourceCode);
        $manageCacheSource = implode("\n", \array_slice($sourceLines, $manageCacheStartLine - 1, $manageCacheEndLine - $manageCacheStartLine + 1));

        // Verify the cache limiting code structure is present and correct
        self::assertStringContainsString('if (\count($cache) >= $this->maxCacheSize)', $manageCacheSource);
        self::assertStringContainsString('reset($cache)', $manageCacheSource);
        self::assertStringContainsString('$firstKey = key($cache)', $manageCacheSource);
        self::assertStringContainsString('unset($cache[$firstKey])', $manageCacheSource);
    }

    /**
     * Verifies protobuf cache limiting via direct cache manipulation.
     */
    public function testProtobufCacheLimiting(): void
    {
        // Create a cache with a small size limit.
        $cache = new LimitedReflectionCache(5);

        // Access private fields and methods for targeted assertions.
        $reflection = new \ReflectionClass(LimitedReflectionCache::class);
        $protobufCacheProperty = $reflection->getProperty('protobufCache');

        $manageCacheMethod = $reflection->getMethod('manageCache');

        // Read the initial cache state.
        /** @var array<string, bool> $cacheData */
        $cacheData = $protobufCacheProperty->getValue($cache);

        // Start with an empty cache.
        self::assertCount(0, $cacheData, 'Initial cache must be empty');

        // Add entries through the internal cache management method.
        for ($i = 0; $i < 10; ++$i) {
            $className = 'TestClass' . $i;
            $value = ($i % 2) === 0;

            /** @var array<string, bool> $cacheRef */
            $cacheRef = $protobufCacheProperty->getValue($cache);
            $manageCacheMethod->invokeArgs($cache, [&$cacheRef, $className, $value]);
            $protobufCacheProperty->setValue($cache, $cacheRef);
        }

        // Read the final cache state.
        /** @var array<string, bool> $finalCacheData */
        $finalCacheData = $protobufCacheProperty->getValue($cache);

        // Verify that the cache size never exceeds the configured limit.
        self::assertLessThanOrEqual(
            5,
            \count($finalCacheData),
            'The protobuf cache size must remain bounded',
        );
    }

    /**
     * Verifies reflection cache limiting with real classes.
     */
    public function testReflectionClassCacheBehavior(): void
    {
        // Create a cache with a very small size limit.
        $cache = new LimitedReflectionCache(3);

        // Access the internal reflection cache.
        $reflection = new \ReflectionClass(LimitedReflectionCache::class);
        $reflectionCacheProperty = $reflection->getProperty('reflectionCache');

        // Use real classes to populate the cache.
        $classesToTest = [
            self::class,
            TestCase::class,
            LimitedReflectionCache::class,
            \stdClass::class,
            \Exception::class,
        ];

        // Populate the cache.
        foreach ($classesToTest as $className) {
            $cache->getReflectionClass($className);
        }

        // Read the resulting cache state.
        /** @var array<string, \ReflectionClass<object>> $reflectionCacheData */
        $reflectionCacheData = $reflectionCacheProperty->getValue($cache);

        // Verify the configured upper bound.
        self::assertLessThanOrEqual(
            3,
            \count($reflectionCacheData),
            'The reflection cache size must remain bounded',
        );
    }

    public function testIsProtobufMessageWithExistingClasses(): void
    {
        $cache = new LimitedReflectionCache();

        $result = $cache->isProtobufMessage(TestCase::class);
        self::assertFalse($result, 'TestCase must not be treated as a Protobuf message');

        $result = $cache->isProtobufMessage(Message::class);
        self::assertFalse($result, 'Message::class must not be treated as its own subclass');
    }

    public function testIsProtobufMessageCaching(): void
    {
        $cache = new LimitedReflectionCache();

        $cache->isProtobufMessage(TestCase::class);

        $reflection = new \ReflectionClass(LimitedReflectionCache::class);
        $protobufCacheProperty = $reflection->getProperty('protobufCache');

        /** @var array<string, bool> $cacheData */
        $cacheData = $protobufCacheProperty->getValue($cache);

        self::assertArrayHasKey(TestCase::class, $cacheData);
        self::assertFalse($cacheData[TestCase::class]);
        $cacheData[TestCase::class] = true;
        $protobufCacheProperty->setValue($cache, $cacheData);

        $result = $cache->isProtobufMessage(TestCase::class);
        self::assertTrue($result, 'The method must return the cached value');
    }
}

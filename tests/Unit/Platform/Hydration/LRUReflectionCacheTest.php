<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Hydration;

use App\Platform\Hydration\HydratorException;
use App\Platform\Hydration\LRUReflectionCache;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Platform\Hydration\Fixtures\TestProtobufMessage;

final class LRUReflectionCacheTest extends TestCase
{
    private LRUReflectionCache $cache;

    protected function setUp(): void
    {
        $this->cache = new LRUReflectionCache(3); // Small cache size for testing.
    }

    public function testGetReflectionClass(): void
    {
        $reflection = $this->cache->getReflectionClass(\stdClass::class);

        self::assertInstanceOf(\ReflectionClass::class, $reflection);
        self::assertEquals(\stdClass::class, $reflection->getName());
    }

    public function testEvictionWhenCacheFull(): void
    {
        // The cache size is fixed at three entries for this test.
        $this->cache->getReflectionClass(\stdClass::class);
        $this->cache->getReflectionClass(\ArrayObject::class);
        $this->cache->getReflectionClass(\DateTimeImmutable::class);

        // Touch DateTimeImmutable to keep it fresh in the LRU order.
        $this->cache->getReflectionClass(\DateTimeImmutable::class);

        // Refresh DateTimeImmutable once more to make it recently used.
        $this->cache->getReflectionClass(\DateTimeImmutable::class);

        // Adding a new class should evict ArrayObject, not DateTimeImmutable.
        $this->cache->getReflectionClass(\Exception::class);

        self::assertEquals(3, $this->cache->getSize());

        // Access the classes that should still be in cache
        $reflection1 = $this->cache->getReflectionClass(\DateTimeImmutable::class);
        $reflection2 = $this->cache->getReflectionClass(\DateTimeImmutable::class);
        $reflection3 = $this->cache->getReflectionClass(\Exception::class);

        self::assertEquals(\DateTimeImmutable::class, $reflection1->getName());
        self::assertEquals(\DateTimeImmutable::class, $reflection2->getName());
        self::assertEquals(\Exception::class, $reflection3->getName());
    }

    public function testGetConstructorParams(): void
    {
        $params = $this->cache->getConstructorParams(\DateTimeImmutable::class);

        self::assertNotEmpty($params);
        self::assertInstanceOf(\ReflectionParameter::class, $params[0]);
    }

    public function testGetConstructorParamsThrowsExceptionForClassWithoutConstructor(): void
    {
        $this->expectException(HydratorException::class);
        $this->expectExceptionMessage('Class stdClass must have a constructor');

        $this->cache->getConstructorParams(\stdClass::class);
    }

    public function testGetPublicProperties(): void
    {
        // Create a test class with public properties.
        $testObj = new class {
            public string $foo = 'bar';

            // Private property is used to verify it is excluded from the public property list.
            private string $baz = 'qux';

            public function getBaz(): string
            {
                return $this->baz;
            }
        };

        $properties = $this->cache->getPublicProperties($testObj::class);

        self::assertCount(1, $properties);
        self::assertEquals('foo', $properties[0]->getName());
    }

    public function testIsProtobufMessage(): void
    {
        // Standard classes must not be detected as Protobuf messages.
        self::assertFalse($this->cache->isProtobufMessage(\stdClass::class));

        // A generated Protobuf message must be detected correctly.
        self::assertTrue($this->cache->isProtobufMessage(TestProtobufMessage::class));

        // Repeated calls should read the same cached result.
        $result1 = $this->cache->isProtobufMessage(\stdClass::class);
        $result2 = $this->cache->isProtobufMessage(\stdClass::class);
        self::assertSame($result1, $result2);
    }

    /**
     * Verifies behavior for non-existent classes.
     */
    public function testIsProtobufMessageWithNonExistentClass(): void
    {
        // @phpstan-ignore-next-line
        self::assertFalse($this->cache->isProtobufMessage('App\NonExistentClass'));
    }

    public function testCacheClearing(): void
    {
        $this->cache->getReflectionClass(\stdClass::class);
        $this->cache->getReflectionClass(\ArrayObject::class);

        self::assertEquals(2, $this->cache->getSize());

        $this->cache->clear();

        self::assertEquals(0, $this->cache->getSize());
    }

    public function testCachingConsistency(): void
    {
        // The first access should initialize the cache.
        $this->cache->getReflectionClass(\DateTimeImmutable::class);

        // Later accesses should return the cached reflection instance.
        $reflection1 = $this->cache->getReflectionClass(\DateTimeImmutable::class);
        $reflection2 = $this->cache->getReflectionClass(\DateTimeImmutable::class);

        // The same reflection instance should be reused.
        self::assertSame($reflection1, $reflection2);
    }
}

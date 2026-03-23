<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Cache;

use App\Platform\Cache\FallbackStorage;
use PHPUnit\Framework\TestCase;

final class FallbackStorageTest extends TestCase
{
    public function testCachesNullValuesAsExistingEntries(): void
    {
        $storage = new FallbackStorage();

        self::assertTrue($storage->set('nullable-key', null));

        self::assertTrue($storage->has('nullable-key'));
        self::assertNull($storage->get('nullable-key'));
    }

    public function testExpiresEntriesWhenTtlIsReached(): void
    {
        $storage = new FallbackStorage();

        self::assertTrue($storage->set('expiring-key', 'value', 0));

        self::assertFalse($storage->has('expiring-key'));
        self::assertNull($storage->get('expiring-key'));
        self::assertNull($storage->getTtl('expiring-key'));
    }

    public function testEvictsLeastRecentlyUsedEntryWhenCapacityIsExceeded(): void
    {
        $storage = new FallbackStorage(maxEntries: 2);

        self::assertTrue($storage->set('first', 'value-1'));
        self::assertTrue($storage->set('second', 'value-2'));

        $this->setLastAccess($storage, 'first', 300);
        $this->setLastAccess($storage, 'second', 100);

        self::assertSame('value-1', $storage->get('first'));
        self::assertTrue($storage->set('third', 'value-3'));

        self::assertTrue($storage->has('first'));
        self::assertFalse($storage->has('second'));
        self::assertTrue($storage->has('third'));
    }

    private function setLastAccess(FallbackStorage $storage, string $key, int $lastAccess): void
    {
        $reflectionProperty = new \ReflectionProperty(FallbackStorage::class, 'entries');
        /** @var array<string, array{value: mixed, expiresAt: int|null, lastAccess: int}> $entries */
        $entries = $reflectionProperty->getValue($storage);

        $entries[$key]['lastAccess'] = $lastAccess;

        $reflectionProperty->setValue($storage, $entries);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Cache;

use App\Platform\Cache\CacheConfig;
use App\Platform\Cache\RoadRunnerCacheService;
use App\Platform\Cache\ScopedCacheFactory;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Platform\Logging\TestLogger;

final class ScopedCacheFactoryTest extends TestCase
{
    public function testCreatesScopedCacheViews(): void
    {
        $cacheService = new RoadRunnerCacheService(
            new CacheConfig(
                engine: 'memory',
                address: 'tcp://localhost:6001',
                defaultPrefix: 'test:',
                namespaceSeed: 'deploy-42',
                defaultTtl: 60,
            ),
            new TestLogger(),
        );

        $storage = new MockStorage();
        $reflection = new \ReflectionProperty($cacheService, 'storage');
        $reflection->setValue($cacheService, $storage);

        $factory = new ScopedCacheFactory($cacheService);
        $sessionCache = $factory->scope('session');

        self::assertTrue($sessionCache->set('abc', 'value'));
        self::assertTrue($sessionCache->has('abc'));
        self::assertSame('value', $sessionCache->get('abc'));
        self::assertSame('session:abc', $sessionCache->key('abc')->toString());
        self::assertTrue($storage->has('test:deploy-42:1:session:abc'));
    }
}

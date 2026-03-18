<?php

declare(strict_types=1);

namespace Tests\Unit\Profile;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Profile\BaseApiTemplateTransportProfile;
use ProtoPhpGen\Profile\TransportProfileRegistry;

final class TransportProfileRegistryTest extends TestCase
{
    public function testReturnsBaseApiTemplateProfile(): void
    {
        $registry = new TransportProfileRegistry();
        $profile = $registry->get(BaseApiTemplateTransportProfile::NAME);

        self::assertInstanceOf(BaseApiTemplateTransportProfile::class, $profile);
        self::assertSame(
            'App\\Generated\\Transport\\Api\\V1\\HealthService',
            $profile->buildServiceNamespace(
                'App\\Generated\\Transport',
                'App\\Api\\V1',
                'HealthService',
            ),
        );
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Profile;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Profile\BaseApiTemplateEndpointProfile;
use ProtoPhpGen\Profile\EndpointProfileRegistry;

final class EndpointProfileRegistryTest extends TestCase
{
    public function testReturnsBaseApiTemplateProfile(): void
    {
        $registry = new EndpointProfileRegistry();
        $profile = $registry->get(BaseApiTemplateEndpointProfile::NAME);

        self::assertInstanceOf(BaseApiTemplateEndpointProfile::class, $profile);
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

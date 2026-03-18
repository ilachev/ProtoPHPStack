<?php

declare(strict_types=1);

namespace Tests\Unit\Profile;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Profile\EndpointProfile;
use ProtoPhpGen\Profile\EndpointProfileResolver;
use Tests\Fixtures\TestEndpointProfile;

final class EndpointProfileResolverTest extends TestCase
{
    public function testResolvesProfileByClassName(): void
    {
        $resolver = new EndpointProfileResolver();
        $profile = $resolver->resolve(TestEndpointProfile::class);

        self::assertInstanceOf(TestEndpointProfile::class, $profile);
        self::assertInstanceOf(EndpointProfile::class, $profile);
    }

    public function testFailsForUnknownClass(): void
    {
        $resolver = new EndpointProfileResolver();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown endpoint profile class');

        $resolver->resolve('Unknown\\Profile');
    }

    public function testFailsForClassWithoutEndpointProfileContract(): void
    {
        $resolver = new EndpointProfileResolver();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Endpoint profile class must implement');

        $resolver->resolve(\stdClass::class);
    }
}

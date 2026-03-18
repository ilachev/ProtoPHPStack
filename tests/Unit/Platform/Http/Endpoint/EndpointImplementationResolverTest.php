<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Http\Endpoint;

use App\Generated\Transport\Api\V1\HealthService\CheckEndpoint;
use App\Platform\Http\Endpoint\EndpointImplementationResolver;
use App\Platform\Http\Endpoint\GeneratedEndpointBindingProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class EndpointImplementationResolverTest extends TestCase
{
    public function testResolvesHandwrittenImplementationFromManifest(): void
    {
        $resolver = new EndpointImplementationResolver(
            new GeneratedEndpointBindingProvider(\dirname(__DIR__, 5) . '/gen/Generated/EndpointBindings'),
        );

        $implementation = $resolver->resolve(CheckEndpoint::class);

        self::assertSame(
            \App\Platform\Http\Endpoint\Api\V1\HealthService\CheckEndpoint::class,
            $implementation,
        );
    }

    public function testReturnsNullForNonGeneratedInterface(): void
    {
        $resolver = new EndpointImplementationResolver(
            new GeneratedEndpointBindingProvider(\dirname(__DIR__, 5) . '/gen/Generated/EndpointBindings'),
        );

        $implementation = $resolver->resolve(ContainerInterface::class);

        self::assertNull($implementation);
    }
}

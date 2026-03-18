<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Http\Endpoint;

use App\Generated\Transport\Api\V1\HealthService\CheckEndpoint;
use App\Platform\Http\Endpoint\EndpointImplementationResolver;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class EndpointImplementationResolverTest extends TestCase
{
    public function testResolvesHandwrittenImplementationByConvention(): void
    {
        $resolver = new EndpointImplementationResolver();

        $implementation = $resolver->resolve(CheckEndpoint::class);

        self::assertSame(
            \App\Platform\Http\Endpoint\Api\V1\HealthService\CheckEndpoint::class,
            $implementation,
        );
    }

    public function testReturnsNullForNonGeneratedInterface(): void
    {
        $resolver = new EndpointImplementationResolver();

        $implementation = $resolver->resolve(ContainerInterface::class);

        self::assertNull($implementation);
    }
}

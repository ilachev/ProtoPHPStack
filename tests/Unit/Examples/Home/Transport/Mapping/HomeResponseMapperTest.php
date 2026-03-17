<?php

declare(strict_types=1);

namespace Tests\Unit\Examples\Home\Transport\Mapping;

use App\Examples\Home\Transport\Mapping\HomeResponseMapper;
use App\Platform\DataMapping\DataTransferObjectMapper;
use App\Platform\Hydration\LimitedReflectionCache;
use App\Platform\Hydration\ReflectionHydrator;
use App\Platform\Hydration\SetterProtobufHydration;
use PHPUnit\Framework\TestCase;

final class HomeResponseMapperTest extends TestCase
{
    public function testToResponse(): void
    {
        $cache = new LimitedReflectionCache();
        $protobufHydration = new SetterProtobufHydration();
        $hydrator = new ReflectionHydrator($cache, $protobufHydration);
        $dtoMapper = new DataTransferObjectMapper($hydrator);
        $mapper = new HomeResponseMapper($dtoMapper);

        $response = $mapper->toResponse('Welcome to our API');

        self::assertNotNull($response->getData());
        self::assertEquals('Welcome to our API', $response->getData()->getMessage());
    }
}

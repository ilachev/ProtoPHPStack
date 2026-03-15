<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Mappers;

use App\Application\Mappers\DataTransferObjectMapper;
use App\Infrastructure\Hydrator\LimitedReflectionCache;
use App\Infrastructure\Hydrator\ReflectionHydrator;
use App\Infrastructure\Hydrator\SetterProtobufHydration;
use App\Modules\Home\Transport\Mapping\HomeResponseMapper;
use PHPUnit\Framework\TestCase;

final class HomeMapperTest extends TestCase
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

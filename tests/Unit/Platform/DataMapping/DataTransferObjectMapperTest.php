<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\DataMapping;

use App\Api\V1\HomeData;
use App\Api\V1\HomeResponse;
use App\Platform\DataMapping\DataTransferObjectMapper;
use App\Platform\Hydration\LimitedReflectionCache;
use App\Platform\Hydration\ReflectionHydrator;
use App\Platform\Hydration\SetterProtobufHydration;
use PHPUnit\Framework\TestCase;

final class DataTransferObjectMapperTest extends TestCase
{
    private DataTransferObjectMapper $mapper;

    protected function setUp(): void
    {
        $cache = new LimitedReflectionCache();
        $protobufHydration = new SetterProtobufHydration();
        $hydrator = new ReflectionHydrator($cache, $protobufHydration);
        $this->mapper = new DataTransferObjectMapper($hydrator);
    }

    public function testToDto(): void
    {
        $data = ['message' => 'Test message'];

        $result = $this->mapper->toDto(HomeData::class, $data);

        self::assertInstanceOf(HomeData::class, $result);
        self::assertEquals('Test message', $result->getMessage());
    }

    public function testToResponse(): void
    {
        $data = ['message' => 'Response data'];

        $result = $this->mapper->toResponse(
            HomeData::class,
            HomeResponse::class,
            $data,
        );

        self::assertInstanceOf(HomeResponse::class, $result);
        self::assertNotNull($result->getData());
        self::assertEquals('Response data', $result->getData()->getMessage());
    }
}

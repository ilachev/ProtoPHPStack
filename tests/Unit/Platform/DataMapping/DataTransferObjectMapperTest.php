<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\DataMapping;

use App\Api\V1\HealthCheckResponse;
use App\Platform\DataMapping\DataTransferObjectMapper;
use App\Platform\Hydration\LimitedReflectionCache;
use App\Platform\Hydration\ReflectionHydrator;
use App\Platform\Hydration\SetterProtobufHydration;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Platform\DataMapping\Fixtures\TestResponse;

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
        $data = [
            'status' => 'ok',
            'timestamp' => 1710000000,
        ];

        $result = $this->mapper->toDto(HealthCheckResponse::class, $data);

        self::assertInstanceOf(HealthCheckResponse::class, $result);
        self::assertEquals('ok', $result->getStatus());
        self::assertEquals(1710000000, $result->getTimestamp());
    }

    public function testToResponse(): void
    {
        $data = [
            'status' => 'ready',
            'timestamp' => 1710001234,
        ];

        $result = $this->mapper->toResponse(
            HealthCheckResponse::class,
            TestResponse::class,
            $data,
        );

        /** @var TestResponse $result */
        self::assertInstanceOf(TestResponse::class, $result);
        $responseData = $result->getData();
        self::assertInstanceOf(HealthCheckResponse::class, $responseData);
        self::assertEquals('ready', $responseData->getStatus());
        self::assertEquals(1710001234, $responseData->getTimestamp());
    }
}

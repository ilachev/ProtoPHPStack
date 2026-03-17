<?php

declare(strict_types=1);

namespace Tests\Unit\Capabilities\ApiStats\Domain;

use App\Capabilities\ApiStats\Domain\ApiCallRecorder;
use App\Capabilities\ApiStats\Domain\ApiStat;
use App\Capabilities\ApiStats\Domain\ApiStatRepository;
use PHPUnit\Framework\TestCase;

final class ApiCallRecorderTest extends TestCase
{
    private TestApiStatRepository $repository;

    private ApiCallRecorder $recorder;

    protected function setUp(): void
    {
        $this->repository = new TestApiStatRepository();
        $this->recorder = new ApiCallRecorder($this->repository);
    }

    public function testRecord(): void
    {
        $stat = new ApiStat(
            id: null,
            sessionId: 'test-session',
            route: '/test/route',
            method: 'GET',
            statusCode: 200,
            executionTime: 123.45,
            requestTime: time(),
        );

        $this->recorder->record($stat);

        self::assertCount(1, $this->repository->stats);
        self::assertSame($stat, $this->repository->stats[0]);
    }
}

/**
 * Test repository for ApiCallRecorder.
 */
final class TestApiStatRepository implements ApiStatRepository
{
    /** @var array<ApiStat> */
    public array $stats = [];

    public function save(ApiStat $stat): void
    {
        $this->stats[] = $stat;
    }
}

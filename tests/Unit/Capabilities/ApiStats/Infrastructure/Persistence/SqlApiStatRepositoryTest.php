<?php

declare(strict_types=1);

namespace Tests\Unit\Capabilities\ApiStats\Infrastructure\Persistence;

use App\Capabilities\ApiStats\Domain\ApiStat;
use App\Capabilities\ApiStats\Infrastructure\Persistence\SqlApiStatRepository;
use App\Platform\Storage\Sql\SqlExecutor;
use App\Platform\Storage\Storage;
use PHPUnit\Framework\TestCase;

final class SqlApiStatRepositoryTest extends TestCase
{
    public function testSaveExecutesGeneratedInsertQuery(): void
    {
        $storage = new InMemoryApiStatStorage();
        $repository = new SqlApiStatRepository(new SqlExecutor($storage));

        $repository->save(
            new ApiStat(
                id: null,
                sessionId: 'session-1',
                route: 'health.check',
                method: 'GET',
                statusCode: 200,
                executionTime: 12.5,
                requestTime: 1_710_000_000,
            ),
        );

        self::assertCount(1, $storage->executedStatements);
        self::assertStringContainsString('INSERT INTO api_stats', $storage->executedStatements[0]['sql']);
        self::assertSame([
            'session_id' => 'session-1',
            'route' => 'health.check',
            'method' => 'GET',
            'status_code' => 200,
            'execution_time' => 12.5,
            'request_time' => 1_710_000_000,
        ], $storage->executedStatements[0]['params']);
    }
}

final class InMemoryApiStatStorage implements Storage
{
    /**
     * @var list<array{sql: string, params: array<string, scalar|null>}>
     */
    public array $executedStatements = [];

    public function query(string $sql, array $params = []): array
    {
        return [];
    }

    public function execute(string $sql, array $params = []): bool
    {
        $this->executedStatements[] = [
            'sql' => $sql,
            'params' => $params,
        ];

        return true;
    }

    public function transaction(callable $callback): mixed
    {
        return $callback();
    }

    public function lastInsertId(): string
    {
        return '0';
    }
}

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

        $savedStat = $repository->save(
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

        self::assertSame(42, $savedStat->id);
        self::assertCount(1, $storage->queriedStatements);
        self::assertStringContainsString('INSERT INTO api_stats', $storage->queriedStatements[0]['sql']);
        self::assertStringContainsString('RETURNING id', $storage->queriedStatements[0]['sql']);
        self::assertSame([
            'session_id' => 'session-1',
            'route' => 'health.check',
            'method' => 'GET',
            'status_code' => 200,
            'execution_time' => 12.5,
            'request_time' => 1_710_000_000,
        ], $storage->queriedStatements[0]['params']);
    }
}

final class InMemoryApiStatStorage implements Storage
{
    /**
     * @var list<array{sql: string, params: array<string, scalar|null>}>
     */
    public array $queriedStatements = [];

    public function query(string $sql, array $params = []): array
    {
        $this->queriedStatements[] = [
            'sql' => $sql,
            'params' => $params,
        ];

        return [['id' => 42]];
    }

    public function execute(string $sql, array $params = []): bool
    {
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

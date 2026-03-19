<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Storage\Sql;

use App\Platform\Storage\Sql\DatabaseRow;
use App\Platform\Storage\Sql\ExecutableQuery;
use App\Platform\Storage\Sql\ManyRowsQuery;
use App\Platform\Storage\Sql\OneRowQuery;
use App\Platform\Storage\Sql\SqlExecutor;
use App\Platform\Storage\Storage;
use PHPUnit\Framework\TestCase;

final class SqlExecutorTest extends TestCase
{
    public function testFetchOneAsMapsRowIntoTypedObject(): void
    {
        $executor = new SqlExecutor(
            new InMemorySqlExecutorStorage([
                ['id' => 'session-1'],
            ]),
        );

        $row = $executor->fetchOneRow(new StubOneRowQuery());

        self::assertInstanceOf(StubDatabaseRow::class, $row);
        self::assertSame('session-1', $row->id);
    }

    public function testFetchAllAsMapsAllRowsIntoTypedObjects(): void
    {
        $executor = new SqlExecutor(
            new InMemorySqlExecutorStorage([
                ['id' => 'session-1'],
                ['id' => 'session-2'],
            ]),
        );

        $rows = $executor->fetchAllRows(new StubManyRowsQuery());

        self::assertCount(2, $rows);
        self::assertInstanceOf(StubDatabaseRow::class, $rows[0]);
        self::assertInstanceOf(StubDatabaseRow::class, $rows[1]);
        self::assertSame('session-1', $rows[0]->id);
        self::assertSame('session-2', $rows[1]->id);
    }
}

/**
 * @implements DatabaseRow<array{id:string}>
 */
final readonly class StubDatabaseRow implements DatabaseRow
{
    public function __construct(
        public string $id,
    ) {}

    /**
     * @param array<string, scalar|null> $row
     */
    public static function fromDatabaseRow(array $row): static
    {
        return new self((string) $row['id']);
    }
}

/**
 * @implements ExecutableQuery<array{}>
 */
final readonly class StubExecutableQuery implements ExecutableQuery
{
    public function sql(): string
    {
        return 'SELECT id FROM sessions';
    }

    /**
     * @return array<string, scalar|null>
     */
    public function params(): array
    {
        return [];
    }
}

/**
 * @implements OneRowQuery<StubDatabaseRow, array{}>
 */
final readonly class StubOneRowQuery implements OneRowQuery
{
    public function sql(): string
    {
        return 'SELECT id FROM sessions';
    }

    public function rowClass(): string
    {
        return StubDatabaseRow::class;
    }

    public function params(): array
    {
        return [];
    }
}

/**
 * @implements ManyRowsQuery<StubDatabaseRow, array{}>
 */
final readonly class StubManyRowsQuery implements ManyRowsQuery
{
    public function sql(): string
    {
        return 'SELECT id FROM sessions';
    }

    /**
     * @return array<string, scalar|null>
     */
    public function params(): array
    {
        return [];
    }

    public function rowClass(): string
    {
        return StubDatabaseRow::class;
    }
}

final readonly class InMemorySqlExecutorStorage implements Storage
{
    /**
     * @param list<array<string, scalar|null>> $rows
     */
    public function __construct(
        private array $rows,
    ) {}

    /**
     * @param array<string, bool|float|int|string|null> $params
     * @return list<array<string, bool|float|int|string|null>>
     */
    public function query(string $sql, array $params = []): array
    {
        return $this->rows;
    }

    /**
     * @param array<string, bool|float|int|string|null> $params
     */
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

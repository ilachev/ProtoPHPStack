<?php

declare(strict_types=1);

namespace Tests\Unit\Parser;

use PHPUnit\Framework\TestCase;
use SqlGen\Ast\DeleteQuery;
use SqlGen\Ast\InsertQuery;
use SqlGen\Ast\SelectProjectionColumn;
use SqlGen\Ast\SelectProjectionWildcard;
use SqlGen\Ast\SelectQuery;
use SqlGen\Parser\PhplrtSqlParser;

final class PhplrtSqlParserTest extends TestCase
{
    public function testParsesSimpleSelect(): void
    {
        $parser = new PhplrtSqlParser();

        $query = $parser->parse(
            'select id, user_id from sessions where id = :id',
        );

        self::assertInstanceOf(SelectQuery::class, $query);
        self::assertSame('sessions', $query->from->table);
        self::assertNull($query->from->alias);
        self::assertCount(2, $query->projections);
        self::assertInstanceOf(SelectProjectionColumn::class, $query->projections[0]);
        self::assertSame('id', $query->projections[0]->reference->column);
        self::assertNull($query->projections[0]->reference->table);
        self::assertCount(1, $query->where);
        self::assertSame('=', $query->where[0]->operator);
    }

    public function testParsesJoinedSelectWithAliases(): void
    {
        $parser = new PhplrtSqlParser();

        $query = $parser->parse(
            'select s.id as session_id, u.email from sessions as s inner join users u on u.id = s.user_id where u.email = :email and s.user_id = :user_id',
        );

        self::assertInstanceOf(SelectQuery::class, $query);
        self::assertSame('sessions', $query->from->table);
        self::assertSame('s', $query->from->alias);
        self::assertCount(2, $query->projections);
        self::assertInstanceOf(SelectProjectionColumn::class, $query->projections[0]);
        self::assertSame('s', $query->projections[0]->reference->table);
        self::assertSame('id', $query->projections[0]->reference->column);
        self::assertSame('session_id', $query->projections[0]->alias?->value);
        self::assertCount(1, $query->joins);
        self::assertSame('inner', $query->joins[0]->type);
        self::assertSame('users', $query->joins[0]->table->table);
        self::assertSame('u', $query->joins[0]->table->alias);
        self::assertCount(2, $query->where);
        self::assertSame(['and'], $query->whereOperators);
    }

    public function testParsesQualifiedWildcardProjection(): void
    {
        $parser = new PhplrtSqlParser();

        $query = $parser->parse(
            'select s.* from sessions s where s.id = :id',
        );

        self::assertInstanceOf(SelectQuery::class, $query);
        self::assertCount(1, $query->projections);
        self::assertInstanceOf(SelectProjectionWildcard::class, $query->projections[0]);
        self::assertSame('s', $query->projections[0]->table);
    }

    public function testParsesSelectWithOrderByClause(): void
    {
        $parser = new PhplrtSqlParser();

        $query = $parser->parse(
            'select id, updated_at from sessions where id = :id order by updated_at desc',
        );

        self::assertInstanceOf(SelectQuery::class, $query);
        self::assertCount(2, $query->projections);
        self::assertInstanceOf(SelectProjectionColumn::class, $query->projections[1]);
        self::assertSame('updated_at', $query->projections[1]->reference->column);
        self::assertCount(1, $query->where);
        self::assertCount(1, $query->orderBy);
        self::assertSame('updated_at', $query->orderBy[0]->column->column);
        self::assertSame('desc', $query->orderBy[0]->direction);
    }

    public function testParsesInsertWithReturningAndConflictClause(): void
    {
        $parser = new PhplrtSqlParser();

        $query = $parser->parse(<<<'SQL'
            INSERT INTO sessions (id, payload)
            VALUES (:id, :payload)
            ON CONFLICT (id) DO UPDATE SET
                payload = EXCLUDED.payload
            RETURNING *;
            SQL);

        self::assertInstanceOf(InsertQuery::class, $query);
        self::assertSame('sessions', $query->table);
        self::assertCount(2, $query->values);
        self::assertSame('id', $query->values[0]->column);
        self::assertSame('id', $query->values[0]->placeholder->name);
        self::assertNotNull($query->conflict);
        self::assertSame(['id'], $query->conflict->targetColumns);
        self::assertCount(1, $query->conflict->assignments);
        self::assertCount(1, $query->returning);
        self::assertInstanceOf(SelectProjectionWildcard::class, $query->returning[0]);
    }

    public function testParsesDeleteWithWhereClause(): void
    {
        $parser = new PhplrtSqlParser();

        $query = $parser->parse(
            'DELETE FROM sessions WHERE expires_at < :now;',
        );

        self::assertInstanceOf(DeleteQuery::class, $query);
        self::assertSame('sessions', $query->table);
        self::assertCount(1, $query->where);
        self::assertSame('<', $query->where[0]->operator);
    }
}

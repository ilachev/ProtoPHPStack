<?php

declare(strict_types=1);

namespace Tests\Unit\Parser;

use PHPUnit\Framework\TestCase;
use SqlGen\Ast\SelectProjectionColumn;
use SqlGen\Ast\SelectProjectionWildcard;
use SqlGen\Parser\PhplrtSelectParser;

final class PhplrtSelectParserTest extends TestCase
{
    public function testParsesSimpleSelect(): void
    {
        $parser = new PhplrtSelectParser();

        $query = $parser->parse(
            'select id, user_id from sessions where id = :id',
        );

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
        $parser = new PhplrtSelectParser();

        $query = $parser->parse(
            'select s.id as session_id, u.email from sessions as s inner join users u on u.id = s.user_id where u.email = :email and s.user_id = :user_id',
        );

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
        $parser = new PhplrtSelectParser();

        $query = $parser->parse(
            'select s.* from sessions s where s.id = :id',
        );

        self::assertCount(1, $query->projections);
        self::assertInstanceOf(SelectProjectionWildcard::class, $query->projections[0]);
        self::assertSame('s', $query->projections[0]->table);
    }
}

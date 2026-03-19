<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;
use SqlGen\Model\DatabaseSchema;
use SqlGen\Model\SchemaColumn;
use SqlGen\Model\SchemaTable;
use SqlGen\Model\SqlResultKind;
use SqlGen\Model\SqlStatement;
use SqlGen\Schema\StatementRowResolver;

final class StatementRowResolverTest extends TestCase
{
    public function testResolvesSimpleSelectedColumns(): void
    {
        $resolver = new StatementRowResolver();
        $schema = new DatabaseSchema([
            'sessions' => new SchemaTable('sessions', [
                'id' => new SchemaColumn('id', 'TEXT', 'string', false),
                'user_id' => new SchemaColumn('user_id', 'BIGINT', 'int', true),
            ]),
        ]);

        $fields = $resolver->resolve(
            new SqlStatement(
                name: 'FindSessions',
                resultKind: SqlResultKind::Many,
                sql: 'SELECT id, user_id FROM sessions;',
                parameters: [],
            ),
            $schema,
        );

        self::assertCount(2, $fields);
        self::assertSame('id', $fields[0]->sourceColumnName);
        self::assertSame('id', $fields[0]->resultColumnName);
        self::assertSame('id', $fields[0]->propertyName);
        self::assertSame('user_id', $fields[1]->sourceColumnName);
        self::assertSame('user_id', $fields[1]->resultColumnName);
        self::assertSame('userId', $fields[1]->propertyName);
    }

    public function testResolvesAliasedSelectedColumns(): void
    {
        $resolver = new StatementRowResolver();
        $schema = new DatabaseSchema([
            'sessions' => new SchemaTable('sessions', [
                'id' => new SchemaColumn('id', 'TEXT', 'string', false),
                'user_id' => new SchemaColumn('user_id', 'BIGINT', 'int', true),
            ]),
        ]);

        $fields = $resolver->resolve(
            new SqlStatement(
                name: 'FindSessions',
                resultKind: SqlResultKind::Many,
                sql: 'SELECT sessions.id AS session_id, user_id AS owner_id FROM sessions;',
                parameters: [],
            ),
            $schema,
        );

        self::assertCount(2, $fields);
        self::assertSame('id', $fields[0]->sourceColumnName);
        self::assertSame('session_id', $fields[0]->resultColumnName);
        self::assertSame('sessionId', $fields[0]->propertyName);
        self::assertSame('user_id', $fields[1]->sourceColumnName);
        self::assertSame('owner_id', $fields[1]->resultColumnName);
        self::assertSame('ownerId', $fields[1]->propertyName);
        self::assertTrue($fields[1]->nullable);
    }

    public function testResolvesReturningColumnsFromInsertStatement(): void
    {
        $resolver = new StatementRowResolver();
        $schema = new DatabaseSchema([
            'users' => new SchemaTable('users', [
                'id' => new SchemaColumn('id', 'BIGSERIAL', 'int', false),
                'email' => new SchemaColumn('email', 'TEXT', 'string', false),
            ]),
        ]);

        $fields = $resolver->resolve(
            new SqlStatement(
                name: 'InsertUser',
                resultKind: SqlResultKind::One,
                sql: <<<'SQL'
                    INSERT INTO users (email)
                    VALUES (:email)
                    RETURNING id, email AS login;
                    SQL,
                parameters: [],
            ),
            $schema,
        );

        self::assertCount(2, $fields);
        self::assertSame('id', $fields[0]->sourceColumnName);
        self::assertSame('id', $fields[0]->resultColumnName);
        self::assertSame('id', $fields[0]->propertyName);
        self::assertSame('email', $fields[1]->sourceColumnName);
        self::assertSame('login', $fields[1]->resultColumnName);
        self::assertSame('login', $fields[1]->propertyName);
    }

    public function testResolvesReturningWildcardFromInsertStatement(): void
    {
        $resolver = new StatementRowResolver();
        $schema = new DatabaseSchema([
            'users' => new SchemaTable('users', [
                'id' => new SchemaColumn('id', 'BIGSERIAL', 'int', false),
                'email' => new SchemaColumn('email', 'TEXT', 'string', false),
            ]),
        ]);

        $fields = $resolver->resolve(
            new SqlStatement(
                name: 'InsertUser',
                resultKind: SqlResultKind::One,
                sql: <<<'SQL'
                    INSERT INTO users (email)
                    VALUES (:email)
                    RETURNING *;
                    SQL,
                parameters: [],
            ),
            $schema,
        );

        self::assertCount(2, $fields);
        self::assertSame('id', $fields[0]->resultColumnName);
        self::assertSame('email', $fields[1]->resultColumnName);
    }
}

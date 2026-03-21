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
use SqlGen\Type\PhpTypeFactory;

final class StatementRowResolverTest extends TestCase
{
    public function testResolvesSimpleSelectedColumns(): void
    {
        $resolver = new StatementRowResolver();
        $schema = new DatabaseSchema([
            'sessions' => new SchemaTable('sessions', [
                'id' => new SchemaColumn('id', 'TEXT', PhpTypeFactory::fromNativeType('string'), false),
                'user_id' => new SchemaColumn('user_id', 'BIGINT', PhpTypeFactory::fromNativeType('int'), true),
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
                'id' => new SchemaColumn('id', 'TEXT', PhpTypeFactory::fromNativeType('string'), false),
                'user_id' => new SchemaColumn('user_id', 'BIGINT', PhpTypeFactory::fromNativeType('int'), true),
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
                'id' => new SchemaColumn('id', 'BIGSERIAL', PhpTypeFactory::fromNativeType('int'), false),
                'email' => new SchemaColumn('email', 'TEXT', PhpTypeFactory::fromNativeType('string'), false),
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
                'id' => new SchemaColumn('id', 'BIGSERIAL', PhpTypeFactory::fromNativeType('int'), false),
                'email' => new SchemaColumn('email', 'TEXT', PhpTypeFactory::fromNativeType('string'), false),
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

    public function testResolvesJoinedSelectedColumnsAcrossMultipleTables(): void
    {
        $resolver = new StatementRowResolver();
        $schema = new DatabaseSchema([
            'sessions' => new SchemaTable('sessions', [
                'id' => new SchemaColumn('id', 'TEXT', PhpTypeFactory::fromNativeType('string'), false),
                'user_id' => new SchemaColumn('user_id', 'BIGINT', PhpTypeFactory::fromNativeType('int'), true),
            ]),
            'users' => new SchemaTable('users', [
                'id' => new SchemaColumn('id', 'BIGSERIAL', PhpTypeFactory::fromNativeType('int'), false),
                'email' => new SchemaColumn('email', 'TEXT', PhpTypeFactory::fromNativeType('string'), false),
            ]),
        ]);

        $fields = $resolver->resolve(
            new SqlStatement(
                name: 'FindSessionOwners',
                resultKind: SqlResultKind::Many,
                sql: <<<'SQL'
                    SELECT s.id AS session_id, u.email AS owner_email
                    FROM sessions AS s
                    INNER JOIN users AS u ON u.id = s.user_id;
                    SQL,
                parameters: [],
            ),
            $schema,
        );

        self::assertCount(2, $fields);
        self::assertSame('id', $fields[0]->sourceColumnName);
        self::assertSame('session_id', $fields[0]->resultColumnName);
        self::assertSame('sessionId', $fields[0]->propertyName);
        self::assertSame('email', $fields[1]->sourceColumnName);
        self::assertSame('owner_email', $fields[1]->resultColumnName);
        self::assertSame('ownerEmail', $fields[1]->propertyName);
        self::assertFalse($fields[1]->nullable);
    }

    public function testRejectsAmbiguousUnqualifiedJoinColumn(): void
    {
        $resolver = new StatementRowResolver();
        $schema = new DatabaseSchema([
            'sessions' => new SchemaTable('sessions', [
                'id' => new SchemaColumn('id', 'TEXT', PhpTypeFactory::fromNativeType('string'), false),
            ]),
            'users' => new SchemaTable('users', [
                'id' => new SchemaColumn('id', 'BIGSERIAL', PhpTypeFactory::fromNativeType('int'), false),
            ]),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Column id is ambiguous');

        $resolver->resolve(
            new SqlStatement(
                name: 'FindSomething',
                resultKind: SqlResultKind::Many,
                sql: <<<'SQL'
                    SELECT id
                    FROM sessions AS s
                    INNER JOIN users AS u ON u.id = s.id;
                    SQL,
                parameters: [],
            ),
            $schema,
        );
    }
}

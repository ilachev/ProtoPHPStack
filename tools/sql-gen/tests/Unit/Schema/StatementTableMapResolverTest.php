<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;
use SqlGen\Model\DatabaseSchema;
use SqlGen\Model\SchemaColumn;
use SqlGen\Model\SchemaForeignKeyConstraint;
use SqlGen\Model\SchemaTable;
use SqlGen\Model\SqlResultKind;
use SqlGen\Model\SqlStatement;
use SqlGen\Schema\StatementTableMapResolver;
use SqlGen\Type\PhpTypeFactory;

final class StatementTableMapResolverTest extends TestCase
{
    public function testResolvesQualifiedColumnsByAlias(): void
    {
        $resolver = new StatementTableMapResolver();
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

        $map = $resolver->resolve(
            new SqlStatement(
                name: 'FindSessionOwners',
                resultKind: SqlResultKind::Many,
                sql: <<<'SQL'
                    SELECT s.id, u.email
                    FROM sessions AS s
                    INNER JOIN users AS u ON u.id = s.user_id;
                    SQL,
                parameters: [],
            ),
            $schema,
        );

        self::assertSame('sessions', $map->resolveColumn('s', 'id')->table->name);
        self::assertSame('users', $map->resolveColumn('u', 'email')->table->name);
    }

    public function testRejectsAmbiguousSelfJoinColumnsWithoutQualifier(): void
    {
        $resolver = new StatementTableMapResolver();
        $schema = new DatabaseSchema([
            'users' => new SchemaTable('users', [
                'id' => new SchemaColumn('id', 'BIGSERIAL', PhpTypeFactory::fromNativeType('int'), false),
            ]),
        ]);

        $map = $resolver->resolve(
            new SqlStatement(
                name: 'FindDuplicateUsers',
                resultKind: SqlResultKind::Many,
                sql: <<<'SQL'
                    SELECT left_user.id, right_user.id
                    FROM users AS left_user
                    INNER JOIN users AS right_user ON right_user.id = left_user.id;
                    SQL,
                parameters: [],
            ),
            $schema,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Column id is ambiguous');

        $map->resolveColumn(null, 'id');
    }

    public function testAcceptsJoinMatchingSchemaForeignKey(): void
    {
        $resolver = new StatementTableMapResolver();
        $schema = new DatabaseSchema([
            'sessions' => new SchemaTable(
                'sessions',
                [
                    'id' => new SchemaColumn('id', 'TEXT', PhpTypeFactory::fromNativeType('string'), false),
                    'user_id' => new SchemaColumn('user_id', 'BIGINT', PhpTypeFactory::fromNativeType('int'), true),
                ],
                foreignKeys: [
                    new SchemaForeignKeyConstraint(
                        columns: ['user_id'],
                        referencedTable: 'users',
                        referencedColumns: ['id'],
                    ),
                ],
            ),
            'users' => new SchemaTable('users', [
                'id' => new SchemaColumn('id', 'BIGSERIAL', PhpTypeFactory::fromNativeType('int'), false),
            ]),
        ]);

        $map = $resolver->resolve(
            new SqlStatement(
                name: 'FindSessionOwners',
                resultKind: SqlResultKind::Many,
                sql: <<<'SQL'
                    SELECT s.id, u.id
                    FROM sessions AS s
                    INNER JOIN users AS u ON s.user_id = u.id;
                    SQL,
                parameters: [],
            ),
            $schema,
        );

        self::assertSame('sessions', $map->resolveColumn('s', 'id')->table->name);
        self::assertSame('users', $map->resolveColumn('u', 'id')->table->name);
    }

    public function testRejectsJoinThatConflictsWithKnownForeignKeyRelation(): void
    {
        $resolver = new StatementTableMapResolver();
        $schema = new DatabaseSchema([
            'sessions' => new SchemaTable(
                'sessions',
                [
                    'id' => new SchemaColumn('id', 'TEXT', PhpTypeFactory::fromNativeType('string'), false),
                    'user_id' => new SchemaColumn('user_id', 'BIGINT', PhpTypeFactory::fromNativeType('int'), true),
                ],
                foreignKeys: [
                    new SchemaForeignKeyConstraint(
                        columns: ['user_id'],
                        referencedTable: 'users',
                        referencedColumns: ['id'],
                    ),
                ],
            ),
            'users' => new SchemaTable('users', [
                'id' => new SchemaColumn('id', 'BIGSERIAL', PhpTypeFactory::fromNativeType('int'), false),
            ]),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not match any schema foreign key relation');

        $resolver->resolve(
            new SqlStatement(
                name: 'FindBrokenSessionOwners',
                resultKind: SqlResultKind::Many,
                sql: <<<'SQL'
                    SELECT s.id, u.id
                    FROM sessions AS s
                    INNER JOIN users AS u ON s.id = u.id;
                    SQL,
                parameters: [],
            ),
            $schema,
        );
    }

    public function testAllowsJoinWithoutSchemaRelation(): void
    {
        $resolver = new StatementTableMapResolver();
        $schema = new DatabaseSchema([
            'sessions' => new SchemaTable('sessions', [
                'id' => new SchemaColumn('id', 'TEXT', PhpTypeFactory::fromNativeType('string'), false),
            ]),
            'audit_logs' => new SchemaTable('audit_logs', [
                'session_id' => new SchemaColumn('session_id', 'TEXT', PhpTypeFactory::fromNativeType('string'), false),
            ]),
        ]);

        $map = $resolver->resolve(
            new SqlStatement(
                name: 'FindSessionsWithLogs',
                resultKind: SqlResultKind::Many,
                sql: <<<'SQL'
                    SELECT s.id, l.session_id
                    FROM sessions AS s
                    INNER JOIN audit_logs AS l ON l.session_id = s.id;
                    SQL,
                parameters: [],
            ),
            $schema,
        );

        self::assertSame('sessions', $map->resolveColumn('s', 'id')->table->name);
        self::assertSame('audit_logs', $map->resolveColumn('l', 'session_id')->table->name);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;
use SqlGen\Model\DatabaseSchema;
use SqlGen\Model\SchemaColumn;
use SqlGen\Model\SchemaTable;
use SqlGen\Model\SqlResultKind;
use SqlGen\Model\SqlStatement;
use SqlGen\Schema\StatementTableMapResolver;

final class StatementTableMapResolverTest extends TestCase
{
    public function testResolvesQualifiedColumnsByAlias(): void
    {
        $resolver = new StatementTableMapResolver();
        $schema = new DatabaseSchema([
            'sessions' => new SchemaTable('sessions', [
                'id' => new SchemaColumn('id', 'TEXT', 'string', false),
                'user_id' => new SchemaColumn('user_id', 'BIGINT', 'int', true),
            ]),
            'users' => new SchemaTable('users', [
                'id' => new SchemaColumn('id', 'BIGSERIAL', 'int', false),
                'email' => new SchemaColumn('email', 'TEXT', 'string', false),
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
                'id' => new SchemaColumn('id', 'BIGSERIAL', 'int', false),
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
}

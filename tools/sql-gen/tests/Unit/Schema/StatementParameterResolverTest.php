<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;
use SqlGen\Model\DatabaseSchema;
use SqlGen\Model\SchemaColumn;
use SqlGen\Model\SchemaTable;
use SqlGen\Model\SqlParameter;
use SqlGen\Model\SqlResultKind;
use SqlGen\Model\SqlStatement;
use SqlGen\Schema\StatementParameterResolver;

final class StatementParameterResolverTest extends TestCase
{
    public function testResolvesParameterTypesFromSchemaColumns(): void
    {
        $resolver = new StatementParameterResolver();
        $schema = new DatabaseSchema([
            'sessions' => new SchemaTable('sessions', [
                'id' => new SchemaColumn('id', 'TEXT', 'string', false),
                'user_id' => new SchemaColumn('user_id', 'INTEGER', 'int', true),
                'expires_at' => new SchemaColumn('expires_at', 'BIGINT', 'int', false),
            ]),
        ]);

        $resolved = $resolver->resolve(
            new SqlStatement(
                name: 'DeleteExpiredSessions',
                resultKind: SqlResultKind::Exec,
                sql: 'DELETE FROM sessions WHERE expires_at < :now;',
                parameters: [new SqlParameter('now')],
            ),
            $schema,
        );

        self::assertCount(1, $resolved);
        self::assertSame('now', $resolved[0]->name);
        self::assertSame('now', $resolved[0]->propertyName);
        self::assertSame('BIGINT', $resolved[0]->sqlType);
        self::assertSame('int', $resolved[0]->phpType);
    }

    public function testResolvesCamelCasePropertyNamesForSnakeCaseParameters(): void
    {
        $resolver = new StatementParameterResolver();
        $schema = new DatabaseSchema([
            'sessions' => new SchemaTable('sessions', [
                'user_id' => new SchemaColumn('user_id', 'INTEGER', 'int', true),
            ]),
        ]);

        $resolved = $resolver->resolve(
            new SqlStatement(
                name: 'FindSessionsByUserId',
                resultKind: SqlResultKind::Many,
                sql: 'SELECT user_id FROM sessions WHERE user_id = :user_id;',
                parameters: [new SqlParameter('user_id')],
            ),
            $schema,
        );

        self::assertCount(1, $resolved);
        self::assertSame('userId', $resolved[0]->propertyName);
        self::assertSame('INTEGER', $resolved[0]->sqlType);
        self::assertSame('int', $resolved[0]->phpType);
    }
}

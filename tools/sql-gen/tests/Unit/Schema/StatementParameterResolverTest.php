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
        self::assertFalse($resolved[0]->nullable);
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
        self::assertTrue($resolved[0]->nullable);
    }

    public function testResolvesInsertValueParameterTypesFromSchemaColumns(): void
    {
        $resolver = new StatementParameterResolver();
        $schema = new DatabaseSchema([
            'api_stats' => new SchemaTable('api_stats', [
                'session_id' => new SchemaColumn('session_id', 'TEXT', 'string', false),
                'status_code' => new SchemaColumn('status_code', 'INTEGER', 'int', false),
                'execution_time' => new SchemaColumn('execution_time', 'REAL', 'float', false),
            ]),
        ]);

        $resolved = $resolver->resolve(
            new SqlStatement(
                name: 'InsertApiStat',
                resultKind: SqlResultKind::Exec,
                sql: <<<'SQL'
                    INSERT INTO api_stats (session_id, status_code, execution_time)
                    VALUES (:session_id, :status_code, :execution_time);
                    SQL,
                parameters: [
                    new SqlParameter('session_id'),
                    new SqlParameter('status_code'),
                    new SqlParameter('execution_time'),
                ],
            ),
            $schema,
        );

        self::assertCount(3, $resolved);
        self::assertSame('string', $resolved[0]->phpType);
        self::assertSame('TEXT', $resolved[0]->sqlType);
        self::assertFalse($resolved[0]->nullable);
        self::assertSame('int', $resolved[1]->phpType);
        self::assertSame('INTEGER', $resolved[1]->sqlType);
        self::assertFalse($resolved[1]->nullable);
        self::assertSame('float', $resolved[2]->phpType);
        self::assertSame('REAL', $resolved[2]->sqlType);
        self::assertFalse($resolved[2]->nullable);
    }

    public function testResolvesUpsertTableFromInsertIntoClause(): void
    {
        $resolver = new StatementParameterResolver();
        $schema = new DatabaseSchema([
            'sessions' => new SchemaTable('sessions', [
                'id' => new SchemaColumn('id', 'TEXT', 'string', false),
                'payload' => new SchemaColumn('payload', 'JSONB', 'string', false),
            ]),
        ]);

        $resolved = $resolver->resolve(
            new SqlStatement(
                name: 'UpsertSession',
                resultKind: SqlResultKind::Exec,
                sql: <<<'SQL'
                    INSERT INTO sessions (id, payload)
                    VALUES (:id, :payload)
                    ON CONFLICT (id) DO UPDATE SET
                        payload = EXCLUDED.payload;
                    SQL,
                parameters: [
                    new SqlParameter('id'),
                    new SqlParameter('payload'),
                ],
            ),
            $schema,
        );

        self::assertCount(2, $resolved);
        self::assertSame('TEXT', $resolved[0]->sqlType);
        self::assertSame('JSONB', $resolved[1]->sqlType);
        self::assertFalse($resolved[0]->nullable);
        self::assertFalse($resolved[1]->nullable);
    }

    public function testResolvesJoinedComparisonParameters(): void
    {
        $resolver = new StatementParameterResolver();
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

        $resolved = $resolver->resolve(
            new SqlStatement(
                name: 'FindSessionOwners',
                resultKind: SqlResultKind::Many,
                sql: <<<'SQL'
                    SELECT s.id, u.email
                    FROM sessions AS s
                    INNER JOIN users AS u ON u.id = s.user_id
                    WHERE u.email = :email AND s.user_id = :user_id;
                    SQL,
                parameters: [
                    new SqlParameter('email'),
                    new SqlParameter('user_id'),
                ],
            ),
            $schema,
        );

        self::assertCount(2, $resolved);
        self::assertSame('email', $resolved[0]->name);
        self::assertSame('string', $resolved[0]->phpType);
        self::assertSame('user_id', $resolved[1]->name);
        self::assertSame('int', $resolved[1]->phpType);
        self::assertTrue($resolved[1]->nullable);
    }

}

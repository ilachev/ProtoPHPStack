<?php

declare(strict_types=1);

namespace Tests\Unit\Check;

use PHPUnit\Framework\TestCase;
use SqlGen\Check\PostgreSqlStatementCompiler;
use SqlGen\Model\DatabaseSchema;
use SqlGen\Model\SchemaColumn;
use SqlGen\Model\SchemaTable;
use SqlGen\Model\SchemaUniqueConstraint;
use SqlGen\Model\SqlParameter;
use SqlGen\Model\SqlResultKind;
use SqlGen\Model\SqlStatement;
use SqlGen\Type\PhpTypeFactory;

final class PostgreSqlStatementCompilerTest extends TestCase
{
    public function testCompilesNamedParametersIntoPreparedStatementTemplate(): void
    {
        $compiler = new PostgreSqlStatementCompiler();
        $schema = new DatabaseSchema([
            'sessions' => new SchemaTable('sessions', [
                'id' => new SchemaColumn('id', 'TEXT', PhpTypeFactory::fromNativeType('string'), false),
                'user_id' => new SchemaColumn('user_id', 'INTEGER', PhpTypeFactory::fromNativeType('int'), true),
            ]),
        ]);

        $compiled = $compiler->compile(
            new SqlStatement(
                name: 'FindSessionById',
                resultKind: SqlResultKind::One,
                sql: 'SELECT id FROM sessions WHERE id = :id OR user_id = :user_id;',
                parameters: [
                    new SqlParameter('id'),
                    new SqlParameter('user_id'),
                ],
            ),
            $schema,
        );

        self::assertSame('FindSessionById', $compiled->name);
        self::assertSame('SELECT id FROM sessions WHERE id = $1 OR user_id = $2', $compiled->sql);
        self::assertSame(['text', 'integer'], $compiled->parameterTypes);
    }

    public function testReusesSamePlaceholderForRepeatedNamedParameter(): void
    {
        $compiler = new PostgreSqlStatementCompiler();
        $schema = new DatabaseSchema([
            'sessions' => new SchemaTable('sessions', [
                'id' => new SchemaColumn('id', 'TEXT', PhpTypeFactory::fromNativeType('string'), false),
            ]),
        ]);

        $compiled = $compiler->compile(
            new SqlStatement(
                name: 'FindSessionById',
                resultKind: SqlResultKind::One,
                sql: 'SELECT id FROM sessions WHERE id = :id OR id = :id;',
                parameters: [
                    new SqlParameter('id'),
                ],
            ),
            $schema,
        );

        self::assertSame('SELECT id FROM sessions WHERE id = $1 OR id = $1', $compiled->sql);
        self::assertSame(['text'], $compiled->parameterTypes);
    }

    public function testCompilesSelectWithOrderByWithoutRegexReplacement(): void
    {
        $compiler = new PostgreSqlStatementCompiler();
        $schema = new DatabaseSchema([
            'sessions' => new SchemaTable('sessions', [
                'id' => new SchemaColumn('id', 'TEXT', PhpTypeFactory::fromNativeType('string'), false),
                'updated_at' => new SchemaColumn('updated_at', 'BIGINT', PhpTypeFactory::fromNativeType('int'), false),
            ]),
        ]);

        $compiled = $compiler->compile(
            new SqlStatement(
                name: 'FindSessions',
                resultKind: SqlResultKind::Many,
                sql: 'SELECT id, updated_at FROM sessions WHERE id = :id ORDER BY updated_at DESC;',
                parameters: [
                    new SqlParameter('id'),
                ],
            ),
            $schema,
        );

        self::assertSame(
            'SELECT id, updated_at FROM sessions WHERE id = $1 ORDER BY updated_at DESC',
            $compiled->sql,
        );
        self::assertSame(['text'], $compiled->parameterTypes);
    }

    public function testCompilesCountProjection(): void
    {
        $compiler = new PostgreSqlStatementCompiler();
        $schema = new DatabaseSchema([
            'sessions' => new SchemaTable('sessions', [
                'user_id' => new SchemaColumn('user_id', 'BIGINT', PhpTypeFactory::fromNativeType('int'), true),
            ]),
        ]);

        $compiled = $compiler->compile(
            new SqlStatement(
                name: 'CountSessions',
                resultKind: SqlResultKind::One,
                sql: 'SELECT COUNT(*) AS total FROM sessions WHERE user_id = :user_id;',
                parameters: [
                    new SqlParameter('user_id'),
                ],
            ),
            $schema,
        );

        self::assertSame(
            'SELECT COUNT(*) AS total FROM sessions WHERE user_id = $1',
            $compiled->sql,
        );
        self::assertSame(['bigint'], $compiled->parameterTypes);
    }

    public function testCompilesInsertWithReturningWithoutRegexReplacement(): void
    {
        $compiler = new PostgreSqlStatementCompiler();
        $schema = new DatabaseSchema([
            'api_stats' => new SchemaTable('api_stats', [
                'session_id' => new SchemaColumn('session_id', 'TEXT', PhpTypeFactory::fromNativeType('string'), false),
                'request_time' => new SchemaColumn('request_time', 'BIGINT', PhpTypeFactory::fromNativeType('int'), false),
                'id' => new SchemaColumn('id', 'BIGSERIAL', PhpTypeFactory::fromNativeType('int'), false),
            ]),
        ]);

        $compiled = $compiler->compile(
            new SqlStatement(
                name: 'InsertApiStat',
                resultKind: SqlResultKind::One,
                sql: <<<'SQL'
                    INSERT INTO api_stats (session_id, request_time)
                    VALUES (:session_id, :request_time)
                    RETURNING id;
                    SQL,
                parameters: [
                    new SqlParameter('session_id'),
                    new SqlParameter('request_time'),
                ],
            ),
            $schema,
        );

        self::assertSame(
            'INSERT INTO api_stats (session_id, request_time) VALUES ($1, $2) RETURNING id',
            $compiled->sql,
        );
        self::assertSame(['text', 'bigint'], $compiled->parameterTypes);
    }

    public function testRejectsInvalidOnConflictTargetAgainstSchemaConstraints(): void
    {
        $compiler = new PostgreSqlStatementCompiler();
        $schema = new DatabaseSchema([
            'users' => new SchemaTable(
                name: 'users',
                columns: [
                    'id' => new SchemaColumn('id', 'BIGSERIAL', PhpTypeFactory::fromNativeType('int'), false, primaryKey: true),
                    'email' => new SchemaColumn('email', 'TEXT', PhpTypeFactory::fromNativeType('string'), false, unique: true),
                    'password_hash' => new SchemaColumn('password_hash', 'TEXT', PhpTypeFactory::fromNativeType('string'), false),
                ],
                primaryKeyColumns: ['id'],
                uniqueConstraints: [
                    new SchemaUniqueConstraint(['email']),
                ],
            ),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ON CONFLICT target (password_hash)');

        $compiler->compile(
            new SqlStatement(
                name: 'UpsertUser',
                resultKind: SqlResultKind::Exec,
                sql: <<<'SQL'
                    INSERT INTO users (email, password_hash)
                    VALUES (:email, :password_hash)
                    ON CONFLICT (password_hash) DO UPDATE SET
                        password_hash = EXCLUDED.password_hash;
                    SQL,
                parameters: [
                    new SqlParameter('email'),
                    new SqlParameter('password_hash'),
                ],
            ),
            $schema,
        );
    }
}

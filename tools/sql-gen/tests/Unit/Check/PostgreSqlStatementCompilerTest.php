<?php

declare(strict_types=1);

namespace Tests\Unit\Check;

use PHPUnit\Framework\TestCase;
use SqlGen\Check\PostgreSqlStatementCompiler;
use SqlGen\Model\DatabaseSchema;
use SqlGen\Model\SchemaColumn;
use SqlGen\Model\SchemaTable;
use SqlGen\Model\SqlParameter;
use SqlGen\Model\SqlResultKind;
use SqlGen\Model\SqlStatement;

final class PostgreSqlStatementCompilerTest extends TestCase
{
    public function testCompilesNamedParametersIntoPreparedStatementTemplate(): void
    {
        $compiler = new PostgreSqlStatementCompiler();
        $schema = new DatabaseSchema([
            'sessions' => new SchemaTable('sessions', [
                'id' => new SchemaColumn('id', 'TEXT', 'string', false),
                'user_id' => new SchemaColumn('user_id', 'INTEGER', 'int', true),
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
                'id' => new SchemaColumn('id', 'TEXT', 'string', false),
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
                'id' => new SchemaColumn('id', 'TEXT', 'string', false),
                'updated_at' => new SchemaColumn('updated_at', 'BIGINT', 'int', false),
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

    public function testCompilesInsertWithReturningWithoutRegexReplacement(): void
    {
        $compiler = new PostgreSqlStatementCompiler();
        $schema = new DatabaseSchema([
            'api_stats' => new SchemaTable('api_stats', [
                'session_id' => new SchemaColumn('session_id', 'TEXT', 'string', false),
                'request_time' => new SchemaColumn('request_time', 'BIGINT', 'int', false),
                'id' => new SchemaColumn('id', 'BIGSERIAL', 'int', false),
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
}

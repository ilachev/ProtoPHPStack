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
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Parser;

use PHPUnit\Framework\TestCase;
use SqlGen\Parser\SqlFileParser;

final class SqlFileParserTest extends TestCase
{
    public function testParsesNamedSqlStatements(): void
    {
        $directory = sys_get_temp_dir() . '/sql-gen-' . bin2hex(random_bytes(8));
        if (!mkdir($directory) && !is_dir($directory)) {
            self::fail('Unable to create temporary SQL directory.');
        }

        $path = $directory . '/session.sql';

        file_put_contents($path, <<<'SQL'
-- name: FindSessionById :one
SELECT *
FROM sessions
WHERE id = :id;

-- name: DeleteExpiredSessions :exec
DELETE FROM sessions
WHERE expires_at < :now;
SQL);

        $parser = new SqlFileParser();
        $sqlFile = $parser->parseFile($path);

        self::assertSame('Session', $sqlFile->moduleName);
        self::assertCount(2, $sqlFile->statements);
        self::assertSame('FindSessionById', $sqlFile->statements[0]->name);
        self::assertSame('id', $sqlFile->statements[0]->parameters[0]->name);
        self::assertSame('DeleteExpiredSessions', $sqlFile->statements[1]->name);
        self::assertSame('now', $sqlFile->statements[1]->parameters[0]->name);

        unlink($path);
        rmdir($directory);
    }
}

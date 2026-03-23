<?php

declare(strict_types=1);

namespace Tests\Unit\Parser;

use PHPUnit\Framework\TestCase;
use SqlGen\Config\SqlArtifactNaming;
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

    public function testIgnoresNamedParameterLookalikesInsideStringsCommentsAndCasts(): void
    {
        $directory = sys_get_temp_dir() . '/sql-gen-' . bin2hex(random_bytes(8));
        if (!mkdir($directory) && !is_dir($directory)) {
            self::fail('Unable to create temporary SQL directory.');
        }

        $path = $directory . '/session.sql';

        file_put_contents($path, <<<'SQL'
-- name: FindSessionById :one
SELECT ':ignored', payload::text
FROM sessions
-- :comment_ignored
WHERE id = :id AND payload = ':still_ignored';
SQL);

        $parser = new SqlFileParser();
        $sqlFile = $parser->parseFile($path);

        self::assertCount(1, $sqlFile->statements);
        self::assertCount(1, $sqlFile->statements[0]->parameters);
        self::assertSame('id', $sqlFile->statements[0]->parameters[0]->name);

        unlink($path);
        rmdir($directory);
    }

    public function testUsesConfigurableArtifactNamingForModuleName(): void
    {
        $directory = sys_get_temp_dir() . '/sql-gen-' . bin2hex(random_bytes(8));
        if (!mkdir($directory) && !is_dir($directory)) {
            self::fail('Unable to create temporary SQL directory.');
        }

        $path = $directory . '/session_store.sql';

        file_put_contents($path, <<<'SQL'
-- name: FindSessionById :one
SELECT id
FROM sessions
WHERE id = :id;
SQL);

        $parser = new SqlFileParser(new readonly class extends SqlArtifactNaming {
            public function moduleNameFromPath(string $path): string
            {
                return 'StorageSession';
            }
        });
        $sqlFile = $parser->parseFile($path);

        self::assertSame('StorageSession', $sqlFile->moduleName);

        unlink($path);
        rmdir($directory);
    }
}

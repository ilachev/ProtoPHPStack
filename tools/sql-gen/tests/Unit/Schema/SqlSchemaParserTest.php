<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;
use SqlGen\Schema\SqlSchemaParser;

final class SqlSchemaParserTest extends TestCase
{
    public function testParsesStructuredSchemaSqlWithoutRegexHeuristics(): void
    {
        $workspace = sys_get_temp_dir() . '/sql-gen-schema-' . uniqid('', true);
        if (!mkdir($workspace, 0777, true) && !is_dir($workspace)) {
            self::fail('Unable to create temporary schema workspace.');
        }

        $schemaPath = $workspace . '/schema.sql';

        file_put_contents($schemaPath, <<<'SQL'
            CREATE TABLE sessions (
                id TEXT PRIMARY KEY,
                user_id INTEGER,
                payload JSONB NOT NULL,
                expires_at BIGINT NOT NULL
            );

            CREATE INDEX idx_sessions_user_id ON sessions(user_id);
            CREATE INDEX idx_sessions_ip ON sessions((payload->>'ip'));

            CREATE TABLE users (
                id BIGSERIAL PRIMARY KEY,
                email TEXT NOT NULL UNIQUE,
                session_id TEXT REFERENCES sessions(id) ON DELETE CASCADE
            );
            SQL);

        $schema = (new SqlSchemaParser())->parseFile($schemaPath);

        $sessions = $schema->getTable('sessions');
        self::assertNotNull($sessions);
        $sessionId = $sessions->getColumn('id');
        self::assertNotNull($sessionId);
        self::assertFalse($sessionId->nullable);
        $sessionUserId = $sessions->getColumn('user_id');
        self::assertNotNull($sessionUserId);
        self::assertTrue($sessionUserId->nullable);
        $sessionPayload = $sessions->getColumn('payload');
        self::assertNotNull($sessionPayload);
        self::assertSame('JSONB', $sessionPayload->sqlType);
        self::assertFalse($sessionPayload->nullable);

        $users = $schema->getTable('users');
        self::assertNotNull($users);
        $userId = $users->getColumn('id');
        self::assertNotNull($userId);
        self::assertSame('BIGSERIAL', $userId->sqlType);
        self::assertFalse($userId->nullable);
        $userEmail = $users->getColumn('email');
        self::assertNotNull($userEmail);
        self::assertFalse($userEmail->nullable);
        $userSessionId = $users->getColumn('session_id');
        self::assertNotNull($userSessionId);
        self::assertTrue($userSessionId->nullable);
    }
}

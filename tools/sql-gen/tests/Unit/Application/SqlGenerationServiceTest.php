<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use PHPUnit\Framework\TestCase;
use SqlGen\Application\SqlGenerationService;
use SqlGen\Config\GeneratorConfig;

final class SqlGenerationServiceTest extends TestCase
{
    public function testGeneratesFilesForAllSqlSourcesInStableOrder(): void
    {
        $workspace = sys_get_temp_dir() . '/sql-gen-service-' . uniqid('', true);
        mkdir($workspace . '/queries', 0777, true);
        file_put_contents(
            $workspace . '/schema.sql',
            <<<'SQL'
            CREATE TABLE users (
                id TEXT PRIMARY KEY
            );
            SQL,
        );
        file_put_contents(
            $workspace . '/queries/beta.sql',
            <<<'SQL'
            -- name: FindBeta :many
            SELECT id
            FROM users;
            SQL,
        );
        file_put_contents(
            $workspace . '/queries/alpha.sql',
            <<<'SQL'
            -- name: FindAlpha :many
            SELECT id
            FROM users;
            SQL,
        );

        $service = new SqlGenerationService();
        $files = $service->generate(
            new GeneratorConfig(
                inputDir: $workspace . '/queries',
                outputDir: 'gen/Generated/Sql',
                namespace: 'App\\Generated\\Sql',
                schemaPath: $workspace . '/schema.sql',
            ),
        );

        self::assertSame('gen/Generated/Sql/Alpha/FindAlphaQuery.php', $files[0]->path);
        self::assertSame('gen/Generated/Sql/Alpha/FindAlphaRow.php', $files[1]->path);
        self::assertSame('gen/Generated/Sql/Beta/FindBetaQuery.php', $files[2]->path);
        self::assertSame('gen/Generated/Sql/Beta/FindBetaRow.php', $files[3]->path);
    }
}

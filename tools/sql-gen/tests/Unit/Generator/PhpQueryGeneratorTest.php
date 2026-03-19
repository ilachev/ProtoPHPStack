<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use PHPUnit\Framework\TestCase;
use SqlGen\Config\GeneratorConfig;
use SqlGen\Generator\PhpQueryGenerator;
use SqlGen\Model\DatabaseSchema;
use SqlGen\Model\SchemaColumn;
use SqlGen\Model\SchemaTable;
use SqlGen\Model\SqlFile;
use SqlGen\Model\SqlParameter;
use SqlGen\Model\SqlResultKind;
use SqlGen\Model\SqlStatement;

final class PhpQueryGeneratorTest extends TestCase
{
    public function testGeneratesParamsQueryAndFacadeFiles(): void
    {
        $generator = new PhpQueryGenerator(
            new GeneratorConfig(
                inputDir: 'sql/queries',
                outputDir: 'gen/Generated/Sql',
                namespace: 'App\\Generated\\Sql',
                schemaPath: 'sql/schema.sql',
            ),
            new DatabaseSchema([
                'sessions' => new SchemaTable('sessions', [
                    'id' => new SchemaColumn('id', 'TEXT', 'string', false),
                ]),
            ]),
        );

        $files = $generator->generateForSqlFile(
            new SqlFile(
                sourcePath: 'sql/queries/session.sql',
                moduleName: 'Session',
                statements: [
                    new SqlStatement(
                        name: 'FindSessionById',
                        resultKind: SqlResultKind::One,
                        sql: 'SELECT * FROM sessions WHERE id = :id;',
                        parameters: [new SqlParameter('id')],
                    ),
                ],
            ),
        );

        self::assertCount(4, $files);
        self::assertSame('gen/Generated/Sql/Session/FindSessionByIdParams.php', $files[0]->path);
        self::assertStringContainsString('final readonly class FindSessionByIdParams', $files[0]->content);
        self::assertStringContainsString('public string|int|float|bool|null $id', $files[0]->content);
        self::assertSame('gen/Generated/Sql/Session/FindSessionByIdRow.php', $files[1]->path);
        self::assertStringContainsString('final readonly class FindSessionByIdRow', $files[1]->content);
        self::assertStringContainsString('fromDatabaseRow', $files[1]->content);
        self::assertStringContainsString('implements ExecutableQuery', $files[2]->content);
        self::assertStringContainsString("return 'FindSessionById';", $files[2]->content);
        self::assertStringContainsString("'id' => \$this->params->id", $files[2]->content);
        self::assertStringContainsString('final readonly class SessionQueries', $files[3]->content);
    }
}

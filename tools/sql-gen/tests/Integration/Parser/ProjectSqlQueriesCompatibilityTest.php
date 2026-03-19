<?php

declare(strict_types=1);

namespace Tests\Integration\Parser;

use PHPUnit\Framework\TestCase;
use SqlGen\Ast\DeleteQuery;
use SqlGen\Ast\InsertQuery;
use SqlGen\Ast\SelectQuery;
use SqlGen\Parser\PhplrtSqlParser;
use SqlGen\Parser\SqlFileParser;

final class ProjectSqlQueriesCompatibilityTest extends TestCase
{
    public function testParsesAllProjectSqlQueries(): void
    {
        $queryFiles = glob($this->projectRoot() . '/sql/queries/*.sql');
        if (!is_array($queryFiles) || $queryFiles === []) {
            self::fail('Project SQL query files were not found.');
        }

        $fileParser = new SqlFileParser();
        $sqlParser = new PhplrtSqlParser();

        foreach ($queryFiles as $queryFile) {
            $sqlFile = $fileParser->parseFile($queryFile);

            foreach ($sqlFile->statements as $statement) {
                $parsed = $sqlParser->parse($statement->sql);

                self::assertTrue(
                    $parsed instanceof SelectQuery || $parsed instanceof InsertQuery || $parsed instanceof DeleteQuery,
                    sprintf('Unsupported AST type for query %s from %s', $statement->name, basename($queryFile)),
                );
            }
        }
    }

    private function projectRoot(): string
    {
        return dirname(__DIR__, 5);
    }
}

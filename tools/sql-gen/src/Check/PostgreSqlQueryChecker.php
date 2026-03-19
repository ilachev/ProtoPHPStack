<?php

declare(strict_types=1);

namespace SqlGen\Check;

use SqlGen\Parser\NamedSqlFileParser;
use SqlGen\Parser\SqlFileParser;
use SqlGen\Schema\DatabaseSchemaParser;
use SqlGen\Schema\SqlSchemaParser;

final readonly class PostgreSqlQueryChecker
{
    private NamedSqlFileParser $sqlFileParser;
    private DatabaseSchemaParser $sqlSchemaParser;
    private PostgreSqlStatementCompiler $statementCompiler;

    public function __construct(
        ?NamedSqlFileParser $sqlFileParser = null,
        ?DatabaseSchemaParser $sqlSchemaParser = null,
        ?PostgreSqlStatementCompiler $statementCompiler = null,
    ) {
        $this->sqlFileParser = $sqlFileParser ?? new SqlFileParser();
        $this->sqlSchemaParser = $sqlSchemaParser ?? new SqlSchemaParser();
        $this->statementCompiler = $statementCompiler ?? new PostgreSqlStatementCompiler();
    }

    public function assertQueriesAreValid(string $inputDir, string $schemaPath, PostgreSqlConnectionConfig $connectionConfig): void
    {
        $schema = $this->sqlSchemaParser->parseFile($schemaPath);
        $queryFiles = glob(rtrim($inputDir, '/') . '/*.sql');
        if (!is_array($queryFiles)) {
            throw new \RuntimeException("Failed to list SQL files in {$inputDir}");
        }

        sort($queryFiles);

        $pdo = $this->createConnection($connectionConfig);
        $schemaName = 'sql_gen_check_' . bin2hex(random_bytes(6));

        try {
            $this->createSchemaSandbox($pdo, $schemaName, $schemaPath);

            foreach ($queryFiles as $queryFile) {
                if (!is_file($queryFile)) {
                    continue;
                }

                try {
                    $sqlFile = $this->sqlFileParser->parseFile($queryFile);
                } catch (\Throwable $exception) {
                    throw new \RuntimeException(
                        sprintf(
                            'Failed to parse SQL query file %s during PostgreSQL validation: %s',
                            $queryFile,
                            $exception->getMessage(),
                        ),
                        0,
                        $exception,
                    );
                }

                foreach ($sqlFile->statements as $statement) {
                    try {
                        $compiled = $this->statementCompiler->compile($statement, $schema);
                        $this->assertPrepared($pdo, $compiled);
                    } catch (\Throwable $exception) {
                        throw new \RuntimeException(
                            sprintf(
                                'PostgreSQL validation failed for query %s from %s: %s',
                                $statement->name,
                                $queryFile,
                                $exception->getMessage(),
                            ),
                            0,
                            $exception,
                        );
                    }
                }
            }
        } finally {
            $this->dropSchemaSandbox($pdo, $schemaName);
        }
    }

    private function createConnection(PostgreSqlConnectionConfig $config): \PDO
    {
        return new \PDO(
            "pgsql:host={$config->host};port={$config->port};dbname={$config->database}",
            $config->username,
            $config->password,
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
    }

    private function createSchemaSandbox(\PDO $pdo, string $schemaName, string $schemaPath): void
    {
        $schemaSql = file_get_contents($schemaPath);
        if (!is_string($schemaSql)) {
            throw new \RuntimeException("Unable to read schema file: {$schemaPath}");
        }

        $pdo->exec(sprintf('CREATE SCHEMA "%s"', $schemaName));
        $pdo->exec(sprintf('SET search_path TO "%s"', $schemaName));
        $pdo->exec($schemaSql);
    }

    private function assertPrepared(\PDO $pdo, PreparedPostgreSqlStatement $statement): void
    {
        $preparedName = 'sql_gen_' . strtolower($statement->name);
        $parameterTypes = $statement->parameterTypes === []
            ? ''
            : '(' . implode(', ', $statement->parameterTypes) . ')';

        try {
            $pdo->exec(sprintf(
                'PREPARE %s%s AS %s',
                $preparedName,
                $parameterTypes,
                $statement->sql,
            ));
        } catch (\PDOException $exception) {
            throw new \RuntimeException($exception->getMessage(), 0, $exception);
        } finally {
            try {
                $pdo->exec(sprintf('DEALLOCATE %s', $preparedName));
            } catch (\PDOException) {
            }
        }
    }

    private function dropSchemaSandbox(\PDO $pdo, string $schemaName): void
    {
        try {
            $pdo->exec('SET search_path TO public');
            $pdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        } catch (\PDOException) {
        }
    }
}

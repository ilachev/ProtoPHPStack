<?php

declare(strict_types=1);

namespace SqlGen\Check;

use SqlGen\Model\DatabaseSchema;
use SqlGen\Model\ResolvedSqlParameter;
use SqlGen\Model\SqlStatement;
use SqlGen\Schema\StatementParameterResolver;

final readonly class PostgreSqlStatementCompiler
{
    private StatementParameterResolver $parameterResolver;

    public function __construct(
        ?StatementParameterResolver $parameterResolver = null,
    ) {
        $this->parameterResolver = $parameterResolver ?? new StatementParameterResolver();
    }

    public function compile(SqlStatement $statement, DatabaseSchema $schema): PreparedPostgreSqlStatement
    {
        $parameters = $this->parameterResolver->resolve($statement, $schema);
        $parameterIndexByName = [];

        foreach ($parameters as $index => $parameter) {
            $parameterIndexByName[$parameter->name] = $index + 1;
        }

        $sql = preg_replace_callback(
            '/(?<!:):(?<name>[A-Za-z_][A-Za-z0-9_]*)/',
            static function (array $matches) use ($parameterIndexByName, $statement): string {
                $name = $matches['name'] ?? null;

                if (!is_string($name) || !isset($parameterIndexByName[$name])) {
                    throw new \RuntimeException(
                        "Unable to compile PostgreSQL statement {$statement->name}: unknown parameter {$name}",
                    );
                }

                return '$' . $parameterIndexByName[$name];
            },
            rtrim($statement->sql, " \t\n\r\0\x0B;"),
        );

        if (!is_string($sql)) {
            throw new \RuntimeException("Unable to compile PostgreSQL statement {$statement->name}");
        }

        return new PreparedPostgreSqlStatement(
            name: $statement->name,
            sql: $sql,
            parameterTypes: array_map(
                fn(ResolvedSqlParameter $parameter): string => $this->mapParameterType($parameter->sqlType),
                $parameters,
            ),
        );
    }

    private function mapParameterType(string $sqlType): string
    {
        return match (strtoupper($sqlType)) {
            'TEXT' => 'text',
            'JSONB' => 'jsonb',
            'INTEGER', 'SERIAL' => 'integer',
            'BIGINT', 'BIGSERIAL' => 'bigint',
            'REAL' => 'real',
            'DOUBLE' => 'double precision',
            'NUMERIC', 'DECIMAL' => 'numeric',
            'BOOLEAN', 'BOOL' => 'boolean',
            default => throw new \RuntimeException("Unsupported PostgreSQL parameter type: {$sqlType}"),
        };
    }
}

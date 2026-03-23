<?php

declare(strict_types=1);

namespace SqlGen\Schema;

use SqlGen\Ast\SelectCaseExpression;
use SqlGen\Ast\SelectColumnReference;
use SqlGen\Ast\SelectFunctionCall;
use SqlGen\Ast\SelectOperand;
use SqlGen\Ast\SelectPlaceholder;
use SqlGen\Model\ResolvedSqlParameter;

use const Typhoon\Type\intT;

use function Typhoon\Type\stringify;

final readonly class SelectProjectionTypeInferrer
{
    /**
     * @param array<string, ResolvedSqlParameter> $parametersByName
     */
    public function inferCaseProjection(
        SelectCaseExpression $expression,
        string $resultColumn,
        StatementTableMap $tableMap,
        string $queryName,
        array $parametersByName = [],
    ): ResolvedProjectionField {
        $resolvedResults = [];

        foreach ($expression->whenClauses as $whenClause) {
            $this->resolveArgument($whenClause->condition->left, $tableMap, $parametersByName, $queryName);
            $this->resolveArgument($whenClause->condition->right, $tableMap, $parametersByName, $queryName);
            $resolvedResults[] = $this->resolveArgument($whenClause->result, $tableMap, $parametersByName, $queryName);
        }

        $resolvedResults[] = $this->resolveArgument($expression->elseResult, $tableMap, $parametersByName, $queryName);

        $firstType = stringify($resolvedResults[0]->phpType);
        foreach ($resolvedResults as $resolvedResult) {
            if (stringify($resolvedResult->phpType) !== $firstType) {
                throw new \RuntimeException(
                    "CASE expression requires compatible result types in query {$queryName}",
                );
            }
        }

        return new ResolvedProjectionField(
            sourceName: $expression->toSql(),
            resultColumn: $resultColumn,
            phpType: $resolvedResults[0]->phpType,
            nullable: array_reduce(
                $resolvedResults,
                static fn(bool $nullable, ResolvedProjectionField $result): bool => $nullable || $result->nullable,
                false,
            ),
        );
    }

    /**
     * @param array<string, ResolvedSqlParameter> $parametersByName
     */
    public function inferFunctionProjection(
        SelectFunctionCall $function,
        string $resultColumn,
        StatementTableMap $tableMap,
        string $queryName,
        array $parametersByName = [],
    ): ResolvedProjectionField {
        $functionName = strtolower($function->name);

        if ($functionName === 'count') {
            foreach ($function->arguments as $argument) {
                $this->resolveArgument($argument, $tableMap, $parametersByName, $queryName);
            }

            return new ResolvedProjectionField(
                sourceName: $function->toSql(),
                resultColumn: $resultColumn,
                phpType: intT,
                nullable: false,
            );
        }

        if (in_array($functionName, ['max', 'min'], true)) {
            if ($function->wildcard || count($function->arguments) !== 1) {
                throw new \RuntimeException(
                    "SQL function {$function->name} requires exactly one argument in query {$queryName}",
                );
            }

            $resolvedArgument = $this->resolveArgument($function->arguments[0], $tableMap, $parametersByName, $queryName);

            return new ResolvedProjectionField(
                sourceName: $function->toSql(),
                resultColumn: $resultColumn,
                phpType: $resolvedArgument->phpType,
                nullable: true,
            );
        }

        if ($functionName === 'coalesce') {
            if ($function->wildcard || count($function->arguments) < 1) {
                throw new \RuntimeException(
                    "SQL function {$function->name} requires at least one argument in query {$queryName}",
                );
            }

            $resolvedArguments = array_map(
                fn(SelectOperand $argument): ResolvedProjectionField => $this->resolveArgument(
                    $argument,
                    $tableMap,
                    $parametersByName,
                    $queryName,
                ),
                $function->arguments,
            );

            $firstType = stringify($resolvedArguments[0]->phpType);
            foreach ($resolvedArguments as $resolvedArgument) {
                if (stringify($resolvedArgument->phpType) !== $firstType) {
                    throw new \RuntimeException(
                        "SQL function {$function->name} requires compatible argument types in query {$queryName}",
                    );
                }
            }

            return new ResolvedProjectionField(
                sourceName: $function->toSql(),
                resultColumn: $resultColumn,
                phpType: $resolvedArguments[0]->phpType,
                nullable: array_reduce(
                    $resolvedArguments,
                    static fn(bool $allNullable, ResolvedProjectionField $argument): bool => $allNullable && $argument->nullable,
                    true,
                ),
            );
        }

        throw new \RuntimeException(
            "Unsupported SQL function {$function->name} in query {$queryName}",
        );
    }

    /**
     * @param array<string, ResolvedSqlParameter> $parametersByName
     */
    private function resolveArgument(
        SelectOperand $argument,
        StatementTableMap $tableMap,
        array $parametersByName,
        string $queryName,
    ): ResolvedProjectionField {
        if ($argument instanceof SelectPlaceholder) {
            $parameter = $parametersByName[$argument->name] ?? null;
            if (!$parameter instanceof ResolvedSqlParameter) {
                throw new \RuntimeException(
                    "Unable to resolve SQL parameter type for projection argument {$argument->name} in query {$queryName}",
                );
            }

            return new ResolvedProjectionField(
                sourceName: ':' . $argument->name,
                resultColumn: $argument->name,
                phpType: $parameter->phpType,
                nullable: $parameter->nullable,
            );
        }

        if (!$argument instanceof SelectColumnReference) {
            throw new \RuntimeException(
                "Unsupported SQL projection argument in query {$queryName}",
            );
        }

        $resolvedColumn = $tableMap->resolveColumn($argument->table, $argument->column);

        return new ResolvedProjectionField(
            sourceName: $argument->table !== null ? $argument->table . '.' . $argument->column : $argument->column,
            resultColumn: $argument->column,
            phpType: $resolvedColumn->column->phpType,
            nullable: $resolvedColumn->column->nullable,
        );
    }
}

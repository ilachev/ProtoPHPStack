<?php

declare(strict_types=1);

namespace SqlGen\Schema;

use SqlGen\Ast\SelectFunctionCall;

use const Typhoon\Type\intT;

final readonly class SelectProjectionTypeInferrer
{
    public function inferFunctionProjection(
        SelectFunctionCall $function,
        string $resultColumn,
        StatementTableMap $tableMap,
        string $queryName,
    ): ResolvedProjectionField {
        $functionName = strtolower($function->name);

        if ($functionName === 'count') {
            if ($function->column !== null) {
                $tableMap->resolveColumn($function->column->table, $function->column->column);
            }

            return new ResolvedProjectionField(
                sourceName: $function->toSql(),
                resultColumn: $resultColumn,
                phpType: intT,
                nullable: false,
            );
        }

        if (in_array($functionName, ['max', 'min'], true)) {
            if ($function->wildcard || $function->column === null) {
                throw new \RuntimeException(
                    "SQL function {$function->name} requires a column argument in query {$queryName}",
                );
            }

            $resolvedColumn = $tableMap->resolveColumn($function->column->table, $function->column->column);

            return new ResolvedProjectionField(
                sourceName: $function->toSql(),
                resultColumn: $resultColumn,
                phpType: $resolvedColumn->column->phpType,
                nullable: true,
            );
        }

        throw new \RuntimeException(
            "Unsupported SQL function {$function->name} in query {$queryName}",
        );
    }
}

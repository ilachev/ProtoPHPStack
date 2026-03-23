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
        if (strtolower($function->name) !== 'count') {
            throw new \RuntimeException(
                "Unsupported SQL function {$function->name} in query {$queryName}",
            );
        }

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
}

<?php

declare(strict_types=1);

namespace SqlGen\Type;

use SqlGen\Model\ResolvedSqlParameter;
use SqlGen\Model\RowField;
use Typhoon\Type;

use function Typhoon\Type\arrayShapeT;

use const Typhoon\Type\boolT;
use const Typhoon\Type\floatT;
use const Typhoon\Type\intT;
use const Typhoon\Type\mixedT;

use function Typhoon\Type\nullOrT;
use function Typhoon\Type\stringify;

use const Typhoon\Type\stringT;

final readonly class PhpDocTypeRenderer
{
    /**
     * @param list<RowField> $fields
     */
    public function renderRowShape(array $fields): string
    {
        $elements = [];

        foreach ($fields as $field) {
            $elements[$field->columnName] = $this->resolveType($field->phpType, $field->nullable);
        }

        return stringify(arrayShapeT($elements));
    }

    /**
     * @param list<ResolvedSqlParameter> $parameters
     */
    public function renderParamsShape(array $parameters): string
    {
        $elements = [];

        foreach ($parameters as $parameter) {
            $elements[$parameter->name] = $this->resolveType($parameter->phpType, $parameter->nullable);
        }

        return stringify(arrayShapeT($elements));
    }

    private function resolveType(string $phpType, bool $nullable): Type
    {
        $type = match ($phpType) {
            'int' => intT,
            'float' => floatT,
            'bool' => boolT,
            'string' => stringT,
            default => mixedT,
        };

        return $nullable ? nullOrT($type) : $type;
    }
}

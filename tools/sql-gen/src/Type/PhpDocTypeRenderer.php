<?php

declare(strict_types=1);

namespace SqlGen\Type;

use SqlGen\Model\ResolvedSqlParameter;
use SqlGen\Model\RowField;

use function Typhoon\Type\arrayShapeT;
use function Typhoon\Type\nullOrT;
use function Typhoon\Type\stringify;

final readonly class PhpDocTypeRenderer
{
    /**
     * @param list<RowField> $fields
     */
    public function renderRowShape(array $fields): string
    {
        $elements = [];

        foreach ($fields as $field) {
            $elements[$field->resultColumnName] = $field->nullable ? nullOrT($field->phpType) : $field->phpType;
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
            $elements[$parameter->name] = $parameter->nullable ? nullOrT($parameter->phpType) : $parameter->phpType;
        }

        return stringify(arrayShapeT($elements));
    }
}

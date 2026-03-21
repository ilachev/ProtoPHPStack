<?php

declare(strict_types=1);

namespace SqlGen\Type;

use Typhoon\Type;

final readonly class DatabaseValueExpressionRenderer
{
    private NativeTypeRenderer $nativeTypeRenderer;

    public function __construct()
    {
        $this->nativeTypeRenderer = new NativeTypeRenderer();
    }

    public function renderArrayValue(Type $type, string $rowVariable, string $columnName, bool $nullable): string
    {
        $source = "{$rowVariable}['{$columnName}']";
        $hasValue = "array_key_exists('{$columnName}', {$rowVariable}) && {$source} !== null";
        $nativeType = $this->nativeTypeRenderer->render($type);
        $cast = match ($nativeType) {
            'int' => '(int)',
            'float' => '(float)',
            'bool' => '(bool)',
            'string' => '(string)',
        };

        if ($nullable) {
            return "{$hasValue} ? {$cast} {$source} : null";
        }

        return "{$hasValue} ? {$cast} {$source} : throw new \\InvalidArgumentException('Missing required column {$columnName}.')";
    }
}

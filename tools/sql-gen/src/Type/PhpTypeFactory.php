<?php

declare(strict_types=1);

namespace SqlGen\Type;

use Typhoon\Type;

use const Typhoon\Type\boolT;
use const Typhoon\Type\floatT;
use const Typhoon\Type\intT;
use const Typhoon\Type\stringT;

final readonly class PhpTypeFactory
{
    public static function fromSqlType(string $sqlType): Type
    {
        return match ($sqlType) {
            'TEXT', 'JSONB' => stringT,
            'INTEGER', 'BIGINT', 'BIGSERIAL', 'SERIAL' => intT,
            'REAL', 'DOUBLE', 'NUMERIC', 'DECIMAL' => floatT,
            'BOOLEAN', 'BOOL' => boolT,
            default => throw new \RuntimeException("Unsupported SQL type for PHP mapping: {$sqlType}"),
        };
    }

    public static function fromNativeType(string $type): Type
    {
        return match ($type) {
            'string' => stringT,
            'int' => intT,
            'float' => floatT,
            'bool' => boolT,
            default => throw new \RuntimeException("Unsupported PHP native type: {$type}"),
        };
    }
}

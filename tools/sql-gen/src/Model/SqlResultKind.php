<?php

declare(strict_types=1);

namespace SqlGen\Model;

enum SqlResultKind: string
{
    case One = 'one';
    case Many = 'many';
    case Exec = 'exec';

    public static function fromDeclaration(string $value): self
    {
        return match ($value) {
            'one' => self::One,
            'many' => self::Many,
            'exec' => self::Exec,
            default => throw new \InvalidArgumentException("Unsupported SQL result kind: {$value}"),
        };
    }
}

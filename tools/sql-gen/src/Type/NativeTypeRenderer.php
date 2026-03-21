<?php

declare(strict_types=1);

namespace SqlGen\Type;

use Typhoon\Type;

use function Typhoon\Type\stringify;

final readonly class NativeTypeRenderer
{
    /**
     * @return 'string'|'int'|'float'|'bool'
     */
    public function render(Type $type): string
    {
        $rendered = stringify($type);

        return match ($rendered) {
            'string', 'int', 'float', 'bool' => $rendered,
            default => throw new \RuntimeException("Unsupported native PHP type rendering: {$rendered}"),
        };
    }
}

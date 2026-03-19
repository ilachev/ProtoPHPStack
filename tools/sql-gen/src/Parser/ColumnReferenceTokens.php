<?php

declare(strict_types=1);

namespace SqlGen\Parser;

use SqlGen\Ast\SelectColumnReference;

final readonly class ColumnReferenceTokens
{
    private TokenValueSequence $tokens;

    /**
     * @param list<string> $values
     */
    public function __construct(array $values)
    {
        $this->tokens = new TokenValueSequence($values);
    }

    public function toColumnReference(): SelectColumnReference
    {
        return match ($this->tokens->count()) {
            1 => new SelectColumnReference(
                table: null,
                column: $this->tokens->first(),
            ),
            3 => new SelectColumnReference(
                table: $this->tokens->first(),
                column: $this->tokens->third(),
            ),
            default => throw new \RuntimeException('ColumnRef token sequence is not supported by sql-gen subset parser.'),
        };
    }
}

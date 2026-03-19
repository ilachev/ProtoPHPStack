<?php

declare(strict_types=1);

namespace SqlGen\Parser;

use SqlGen\Ast\SelectTableReference;

final readonly class TableReferenceTokens
{
    private TokenValueSequence $tokens;

    /**
     * @param list<string> $values
     */
    public function __construct(array $values)
    {
        $this->tokens = new TokenValueSequence($values);
    }

    public function toTableReference(): SelectTableReference
    {
        if ($this->tokens->isEmpty()) {
            throw new \RuntimeException('TableRef must contain at least one token.');
        }

        return match ($this->tokens->count()) {
            1 => new SelectTableReference(
                table: $this->tokens->first(),
                alias: null,
            ),
            2 => new SelectTableReference(
                table: $this->tokens->first(),
                alias: $this->tokens->second(),
            ),
            3 => $this->tokens->second() === 'as' || $this->tokens->second() === 'AS'
                ? new SelectTableReference(
                    table: $this->tokens->first(),
                    alias: $this->tokens->third(),
                )
                : throw new \RuntimeException('TableRef token sequence is not supported by sql-gen subset parser.'),
            default => throw new \RuntimeException('TableRef token sequence is not supported by sql-gen subset parser.'),
        };
    }
}

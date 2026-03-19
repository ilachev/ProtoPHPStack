<?php

declare(strict_types=1);

namespace SqlGen\Parser;

use SqlGen\Ast\SelectAlias;

final readonly class SelectAliasTokens
{
    private TokenValueSequence $tokens;

    /**
     * @param list<string> $values
     */
    public function __construct(array $values)
    {
        $this->tokens = new TokenValueSequence($values);
    }

    public function toAlias(): ?SelectAlias
    {
        if ($this->tokens->isEmpty()) {
            return null;
        }

        if ($this->tokens->count() === 1) {
            return new SelectAlias($this->tokens->first());
        }

        if ($this->tokens->count() === 2 && strtolower($this->tokens->first()) === 'as') {
            return new SelectAlias($this->tokens->second());
        }

        throw new \RuntimeException('AliasedColumn direct token sequence is not supported by sql-gen subset parser.');
    }
}

<?php

declare(strict_types=1);

namespace SqlGen\Parser;

use SqlGen\Ast\SelectPlaceholder;

final readonly class PlaceholderTokens
{
    private TokenValueSequence $tokens;

    /**
     * @param list<string> $values
     */
    public function __construct(array $values)
    {
        $this->tokens = new TokenValueSequence($values);
    }

    public function toPlaceholder(): SelectPlaceholder
    {
        if ($this->tokens->count() !== 2 || $this->tokens->first() !== ':') {
            throw new \RuntimeException('Placeholder token sequence is not supported by sql-gen subset parser.');
        }

        return new SelectPlaceholder($this->tokens->second());
    }
}

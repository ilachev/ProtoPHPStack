<?php

declare(strict_types=1);

namespace SqlGen\Parser;

final readonly class ExcludedReferenceTokens
{
    private TokenValueSequence $tokens;

    /**
     * @param list<string> $values
     */
    public function __construct(array $values)
    {
        $this->tokens = new TokenValueSequence($values);
    }

    public function column(): string
    {
        if ($this->tokens->count() !== 3 || strtolower($this->tokens->first()) !== 'excluded') {
            throw new \RuntimeException('ExcludedRef token sequence is not supported by sql-gen subset parser.');
        }

        return $this->tokens->third();
    }
}

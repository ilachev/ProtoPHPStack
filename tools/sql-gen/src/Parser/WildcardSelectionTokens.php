<?php

declare(strict_types=1);

namespace SqlGen\Parser;

use SqlGen\Ast\SelectProjectionWildcard;

final readonly class WildcardSelectionTokens
{
    private TokenValueSequence $tokens;

    /**
     * @param list<string> $values
     */
    public function __construct(array $values)
    {
        $this->tokens = new TokenValueSequence($values);
    }

    public function toProjectionWildcard(): SelectProjectionWildcard
    {
        return match ($this->tokens->count()) {
            1 => new SelectProjectionWildcard(table: null),
            3 => new SelectProjectionWildcard(table: $this->tokens->first()),
            default => throw new \RuntimeException('WildcardSelection token sequence is not supported by sql-gen subset parser.'),
        };
    }
}

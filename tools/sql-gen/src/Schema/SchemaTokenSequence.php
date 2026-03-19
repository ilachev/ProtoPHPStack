<?php

declare(strict_types=1);

namespace SqlGen\Schema;

final readonly class SchemaTokenSequence
{
    /**
     * @param list<SchemaToken> $tokens
     */
    public function __construct(
        private array $tokens,
    ) {}

    public function first(): SchemaToken
    {
        return $this->tokenAt(0, 'first token');
    }

    public function second(): SchemaToken
    {
        return $this->tokenAt(1, 'second token');
    }

    public function next(int $offset): ?SchemaToken
    {
        return $this->tokens[$offset + 1] ?? null;
    }

    public function tokenAt(int $offset, string $context): SchemaToken
    {
        $token = $this->tokens[$offset] ?? null;
        if (!$token instanceof SchemaToken) {
            throw new \RuntimeException("Expected {$context} in schema token sequence.");
        }

        return $token;
    }
}

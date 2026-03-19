<?php

declare(strict_types=1);

namespace SqlGen\Parser;

final readonly class TokenValueSequence
{
    /**
     * @param list<string> $values
     */
    public function __construct(
        private array $values,
    ) {}

    public function count(): int
    {
        return count($this->values);
    }

    public function isEmpty(): bool
    {
        return $this->values === [];
    }

    public function first(): string
    {
        return $this->valueAt(0, 'first token');
    }

    public function second(): string
    {
        return $this->valueAt(1, 'second token');
    }

    public function third(): string
    {
        return $this->valueAt(2, 'third token');
    }

    public function last(): string
    {
        if ($this->values === []) {
            throw new \RuntimeException('Expected last token, but sequence is empty.');
        }

        return $this->values[array_key_last($this->values)];
    }

    private function valueAt(int $offset, string $context): string
    {
        $value = $this->values[$offset] ?? null;
        if (!is_string($value)) {
            throw new \RuntimeException("Expected {$context}, but token sequence is shorter.");
        }

        return $value;
    }
}

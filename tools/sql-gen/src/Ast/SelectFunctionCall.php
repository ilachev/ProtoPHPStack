<?php

declare(strict_types=1);

namespace SqlGen\Ast;

final readonly class SelectFunctionCall
{
    /**
     * @param list<SelectOperand> $arguments
     */
    public function __construct(
        public string $name,
        public array $arguments,
        public bool $wildcard,
    ) {}

    public function toSql(): string
    {
        if ($this->wildcard) {
            return strtoupper($this->name) . '(*)';
        }

        if ($this->arguments === []) {
            throw new \RuntimeException('Function call must have a wildcard or at least one argument.');
        }

        $arguments = implode(', ', array_map(
            static fn(SelectOperand $argument): string => match (true) {
                $argument instanceof SelectColumnReference => $argument->table !== null
                    ? $argument->table . '.' . $argument->column
                    : $argument->column,
                $argument instanceof SelectPlaceholder => ':' . $argument->name,
                default => throw new \RuntimeException('Unsupported function argument type.'),
            },
            $this->arguments,
        ));

        return strtoupper($this->name) . '(' . $arguments . ')';
    }
}

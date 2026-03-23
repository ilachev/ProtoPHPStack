<?php

declare(strict_types=1);

namespace SqlGen\Ast;

final readonly class SelectFunctionCall
{
    public function __construct(
        public string $name,
        public ?SelectColumnReference $column,
        public bool $wildcard,
    ) {}

    public function toSql(): string
    {
        if ($this->wildcard) {
            return strtoupper($this->name) . '(*)';
        }

        if (!$this->column instanceof SelectColumnReference) {
            throw new \RuntimeException('Function call must have a wildcard or column argument.');
        }

        $column = $this->column->table !== null
            ? $this->column->table . '.' . $this->column->column
            : $this->column->column;

        return strtoupper($this->name) . '(' . $column . ')';
    }
}

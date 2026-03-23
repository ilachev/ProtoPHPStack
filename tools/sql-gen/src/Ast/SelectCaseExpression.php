<?php

declare(strict_types=1);

namespace SqlGen\Ast;

final readonly class SelectCaseExpression
{
    /**
     * @param list<SelectCaseWhen> $whenClauses
     */
    public function __construct(
        public array $whenClauses,
        public SelectOperand $elseResult,
    ) {}

    public function toSql(): string
    {
        $parts = ['CASE'];

        foreach ($this->whenClauses as $whenClause) {
            $parts[] = 'WHEN ' . self::renderComparison($whenClause->condition);
            $parts[] = 'THEN ' . self::renderOperand($whenClause->result);
        }

        $parts[] = 'ELSE ' . self::renderOperand($this->elseResult);
        $parts[] = 'END';

        return implode(' ', $parts);
    }

    private static function renderComparison(SelectComparison $comparison): string
    {
        return self::renderOperand($comparison->left)
            . ' ' . $comparison->operator . ' '
            . self::renderOperand($comparison->right);
    }

    private static function renderOperand(SelectOperand $operand): string
    {
        return match (true) {
            $operand instanceof SelectColumnReference => $operand->table !== null
                ? $operand->table . '.' . $operand->column
                : $operand->column,
            $operand instanceof SelectPlaceholder => ':' . $operand->name,
            default => throw new \RuntimeException('Unsupported CASE operand type.'),
        };
    }
}

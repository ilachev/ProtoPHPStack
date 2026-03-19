<?php

declare(strict_types=1);

namespace SqlGen\Parser;

use Phplrt\Compiler\Compiler;
use Phplrt\Compiler\Runtime\PrintableNode;
use Phplrt\Contracts\Lexer\TokenInterface;
use SqlGen\Ast\SelectAlias;
use SqlGen\Ast\SelectColumnReference;
use SqlGen\Ast\SelectComparison;
use SqlGen\Ast\SelectJoin;
use SqlGen\Ast\SelectOperand;
use SqlGen\Ast\SelectPlaceholder;
use SqlGen\Ast\SelectProjection;
use SqlGen\Ast\SelectProjectionColumn;
use SqlGen\Ast\SelectProjectionWildcard;
use SqlGen\Ast\SelectQuery;
use SqlGen\Ast\SelectTableReference;

final class PhplrtSelectParser
{
    private readonly Compiler $compiler;

    public function __construct()
    {
        $grammar = file_get_contents(__DIR__ . '/../../spikes/phplrt/select_subset.pp2');
        if (!is_string($grammar)) {
            throw new \RuntimeException('Unable to load phplrt SELECT subset grammar.');
        }

        $this->compiler = new Compiler();
        $this->compiler->load($grammar);
    }

    public function parse(string $sql): SelectQuery
    {
        /** @var PrintableNode $parsed */
        $parsed = $this->compiler->parse($sql);

        $select = $this->findFirstChildNode($parsed, 'SelectStmt');
        if (!$select instanceof PrintableNode) {
            throw new \RuntimeException('Unable to locate SelectStmt node in parsed SQL AST.');
        }

        $selectList = $this->findFirstChildNode($select, 'SelectList');
        $from = $this->findFirstChildNode($select, 'TableRef');

        if (!$selectList instanceof PrintableNode || !$from instanceof PrintableNode) {
            throw new \RuntimeException('Parsed SELECT query is missing SelectList or TableRef.');
        }

        $joins = [];
        $where = [];
        $whereOperators = [];

        foreach ($select->children as $child) {
            if (!$child instanceof PrintableNode) {
                continue;
            }

            if ($child->getState() === 'JoinClause') {
                $joins[] = $this->normalizeJoinClause($child);
                continue;
            }

            if ($child->getState() === 'WhereClause') {
                [$where, $whereOperators] = $this->normalizeWhereClause($child);
            }
        }

        return new SelectQuery(
            projections: $this->normalizeSelectList($selectList),
            from: $this->normalizeTableRef($from),
            joins: $joins,
            where: $where,
            whereOperators: $whereOperators,
        );
    }

    /**
     * @return list<SelectProjection>
     */
    private function normalizeSelectList(PrintableNode $selectList): array
    {
        $items = [];

        foreach ($selectList->children as $child) {
            if ($child instanceof PrintableNode && $child->getState() === 'SelectItem') {
                $items[] = $this->normalizeSelectItem($child);
            }
        }

        return $items;
    }

    private function normalizeSelectItem(PrintableNode $item): SelectProjection
    {
        $wildcard = $this->findFirstChildNode($item, 'WildcardSelection');
        if ($wildcard instanceof PrintableNode) {
            return $this->normalizeWildcardSelection($wildcard);
        }

        $column = $this->findFirstChildNode($item, 'AliasedColumn');
        if (!$column instanceof PrintableNode) {
            throw new \RuntimeException('SelectItem must contain AliasedColumn or WildcardSelection.');
        }

        $columnRef = $this->findFirstChildNode($column, 'ColumnRef');
        if (!$columnRef instanceof PrintableNode) {
            throw new \RuntimeException('AliasedColumn must contain ColumnRef.');
        }

        $directTokens = [];
        foreach ($column->children as $child) {
            if ($child instanceof TokenInterface) {
                $directTokens[] = $child->getValue();
            }
        }

        $alias = $directTokens === [] ? null : new SelectAlias($directTokens[count($directTokens) - 1]);

        return new SelectProjectionColumn(
            reference: $this->normalizeColumnRef($columnRef),
            alias: $alias,
        );
    }

    private function normalizeWildcardSelection(PrintableNode $wildcard): SelectProjectionWildcard
    {
        $tokens = $this->tokens($wildcard);

        return new SelectProjectionWildcard(
            table: count($tokens) === 3 ? $tokens[0] : null,
        );
    }

    private function normalizeTableRef(PrintableNode $tableRef): SelectTableReference
    {
        $tokens = $this->tokens($tableRef);
        if ($tokens === []) {
            throw new \RuntimeException('TableRef must contain at least one token.');
        }

        $lastToken = $tokens[count($tokens) - 1];

        return new SelectTableReference(
            table: $tokens[0],
            alias: $lastToken !== $tokens[0] ? $lastToken : null,
        );
    }

    private function normalizeJoinClause(PrintableNode $join): SelectJoin
    {
        $joinTypeNode = $this->findFirstChildNode($join, 'JoinType');
        $tableNode = $this->findNthChildNode($join, 'TableRef', 0);
        $comparisonNode = $this->findFirstChildNode($join, 'ComparisonExpr');

        if (!$tableNode instanceof PrintableNode || !$comparisonNode instanceof PrintableNode) {
            throw new \RuntimeException('JoinClause must contain TableRef and ComparisonExpr.');
        }

        return new SelectJoin(
            type: $joinTypeNode instanceof PrintableNode ? strtolower($this->firstTokenValue($joinTypeNode)) : null,
            table: $this->normalizeTableRef($tableNode),
            condition: $this->normalizeComparison($comparisonNode),
        );
    }

    /**
     * @return array{0: list<SelectComparison>, 1: list<string>}
     */
    private function normalizeWhereClause(PrintableNode $where): array
    {
        $comparisons = [];
        $operators = [];

        foreach ($where->children as $child) {
            if ($child instanceof PrintableNode && $child->getState() === 'ComparisonExpr') {
                $comparisons[] = $this->normalizeComparison($child);
                continue;
            }

            if ($child instanceof TokenInterface && in_array($child->getName(), ['T_AND', 'T_OR'], true)) {
                $operators[] = strtolower($child->getValue());
            }
        }

        return [$comparisons, $operators];
    }

    private function normalizeComparison(PrintableNode $comparison): SelectComparison
    {
        $operands = [];
        $operator = null;

        foreach ($comparison->children as $child) {
            if ($child instanceof PrintableNode && $child->getState() === 'Operand') {
                $operands[] = $this->normalizeOperand($child);
                continue;
            }

            if ($child instanceof PrintableNode && $child->getState() === 'CompareOp') {
                $operator = $this->firstTokenValue($child);
            }
        }

        if (count($operands) !== 2 || !is_string($operator)) {
            throw new \RuntimeException('ComparisonExpr must contain two operands and one operator.');
        }

        return new SelectComparison(
            left: $operands[0],
            operator: $operator,
            right: $operands[1],
        );
    }

    private function normalizeOperand(PrintableNode $operand): SelectOperand
    {
        $column = $this->findFirstChildNode($operand, 'ColumnRef');
        if ($column instanceof PrintableNode) {
            return $this->normalizeColumnRef($column);
        }

        $placeholder = $this->findFirstChildNode($operand, 'Placeholder');
        if (!$placeholder instanceof PrintableNode) {
            throw new \RuntimeException('Operand must contain ColumnRef or Placeholder.');
        }

        $tokens = $this->tokens($placeholder);
        if (!isset($tokens[1])) {
            throw new \RuntimeException('Placeholder must contain a parameter name token.');
        }

        return new SelectPlaceholder($tokens[1]);
    }

    private function normalizeColumnRef(PrintableNode $columnRef): SelectColumnReference
    {
        $tokens = $this->tokens($columnRef);

        return match (count($tokens)) {
            3 => new SelectColumnReference(
                table: $tokens[0],
                column: $tokens[2],
            ),
            1 => new SelectColumnReference(
                table: null,
                column: $tokens[0],
            ),
            default => throw new \RuntimeException('ColumnRef token sequence is not supported by sql-gen subset parser.'),
        };
    }

    private function firstTokenValue(PrintableNode $node): string
    {
        $tokens = $this->tokens($node);
        if ($tokens === []) {
            throw new \RuntimeException('Node does not contain direct tokens.');
        }

        return $tokens[0];
    }

    /**
     * @return list<string>
     */
    private function tokens(PrintableNode $node): array
    {
        $tokens = [];

        foreach ($node->children as $child) {
            if ($child instanceof TokenInterface) {
                $tokens[] = $child->getValue();
            }
        }

        return $tokens;
    }

    private function findFirstChildNode(PrintableNode $node, string $state): ?PrintableNode
    {
        foreach ($node->children as $child) {
            if ($child instanceof PrintableNode && $child->getState() === $state) {
                return $child;
            }
        }

        return null;
    }

    private function findNthChildNode(PrintableNode $node, string $state, int $index): ?PrintableNode
    {
        $matches = [];

        foreach ($node->children as $child) {
            if ($child instanceof PrintableNode && $child->getState() === $state) {
                $matches[] = $child;
            }
        }

        return $matches[$index] ?? null;
    }
}

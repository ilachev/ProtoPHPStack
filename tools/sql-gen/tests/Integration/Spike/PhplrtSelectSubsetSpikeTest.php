<?php

declare(strict_types=1);

namespace Tests\Integration\Spike;

use Phplrt\Compiler\Compiler;
use Phplrt\Compiler\Runtime\PrintableNode;
use Phplrt\Contracts\Lexer\TokenInterface;
use PHPUnit\Framework\TestCase;

final class PhplrtSelectSubsetSpikeTest extends TestCase
{
    public function testParsesSimpleSelectIntoPredictableShape(): void
    {
        $shape = $this->parseToShape(
            'select id, user_id from sessions where id = :id',
        );

        self::assertSame([
            'type' => 'select',
            'columns' => [
                [
                    'type' => 'column',
                    'table' => null,
                    'name' => 'id',
                    'alias' => null,
                ],
                [
                    'type' => 'column',
                    'table' => null,
                    'name' => 'user_id',
                    'alias' => null,
                ],
            ],
            'from' => [
                'table' => 'sessions',
                'alias' => null,
            ],
            'joins' => [],
            'where' => [
                [
                    'operator' => '=',
                    'left' => [
                        'type' => 'column',
                        'table' => null,
                        'name' => 'id',
                    ],
                    'right' => [
                        'type' => 'placeholder',
                        'name' => 'id',
                    ],
                ],
            ],
            'whereOperators' => [],
        ], $shape);
    }

    public function testParsesJoinedSelectIntoNormalizedShape(): void
    {
        $shape = $this->parseToShape(
            'select s.id as session_id, u.email from sessions as s inner join users u on u.id = s.user_id where u.email = :email and s.user_id = :user_id',
        );

        self::assertSame([
            'type' => 'select',
            'columns' => [
                [
                    'type' => 'column',
                    'table' => 's',
                    'name' => 'id',
                    'alias' => 'session_id',
                ],
                [
                    'type' => 'column',
                    'table' => 'u',
                    'name' => 'email',
                    'alias' => null,
                ],
            ],
            'from' => [
                'table' => 'sessions',
                'alias' => 's',
            ],
            'joins' => [
                [
                    'type' => 'inner',
                    'table' => [
                        'table' => 'users',
                        'alias' => 'u',
                    ],
                    'on' => [
                        'operator' => '=',
                        'left' => [
                            'type' => 'column',
                            'table' => 'u',
                            'name' => 'id',
                        ],
                        'right' => [
                            'type' => 'column',
                            'table' => 's',
                            'name' => 'user_id',
                        ],
                    ],
                ],
            ],
            'where' => [
                [
                    'operator' => '=',
                    'left' => [
                        'type' => 'column',
                        'table' => 'u',
                        'name' => 'email',
                    ],
                    'right' => [
                        'type' => 'placeholder',
                        'name' => 'email',
                    ],
                ],
                [
                    'operator' => '=',
                    'left' => [
                        'type' => 'column',
                        'table' => 's',
                        'name' => 'user_id',
                    ],
                    'right' => [
                        'type' => 'placeholder',
                        'name' => 'user_id',
                    ],
                ],
            ],
            'whereOperators' => ['and'],
        ], $shape);
    }

    /**
     * @return array{
     *     type: 'select',
     *     columns: list<array{type: 'column'|'wildcard', table: string|null, name?: string, alias: string|null}>,
     *     from: array{table: string, alias: string|null},
     *     joins: list<array{
     *         type: string|null,
     *         table: array{table: string, alias: string|null},
     *         on: array{
     *             operator: string,
     *             left: array{type: 'column'|'placeholder', table?: string|null, name: string},
     *             right: array{type: 'column'|'placeholder', table?: string|null, name: string}
     *         }
     *     }>,
     *     where: list<array{
     *         operator: string,
     *         left: array{type: 'column'|'placeholder', table?: string|null, name: string},
     *         right: array{type: 'column'|'placeholder', table?: string|null, name: string}
     *     }>,
     *     whereOperators: list<string>
     * }
     */
    private function parseToShape(string $sql): array
    {
        $compiler = new Compiler();
        $grammar = file_get_contents(__DIR__ . '/../../../spikes/phplrt/select_subset.pp2');
        self::assertIsString($grammar);

        $compiler->load($grammar);

        $parsed = $compiler->parse($sql);
        self::assertInstanceOf(PrintableNode::class, $parsed);

        $select = $this->findFirstChildNode($parsed, 'SelectStmt');
        self::assertInstanceOf(PrintableNode::class, $select);

        $selectList = $this->findFirstChildNode($select, 'SelectList');
        $from = $this->findFirstChildNode($select, 'TableRef');

        self::assertInstanceOf(PrintableNode::class, $selectList);
        self::assertInstanceOf(PrintableNode::class, $from);

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

        return [
            'type' => 'select',
            'columns' => $this->normalizeSelectList($selectList),
            'from' => $this->normalizeTableRef($from),
            'joins' => $joins,
            'where' => $where,
            'whereOperators' => $whereOperators,
        ];
    }

    /**
     * @return list<array{type: 'column'|'wildcard', table: string|null, name?: string, alias: string|null}>
     */
    private function normalizeSelectList(PrintableNode $selectList): array
    {
        $items = [];

        foreach ($selectList->children as $child) {
            if (!$child instanceof PrintableNode || $child->getState() !== 'SelectItem') {
                continue;
            }

            $items[] = $this->normalizeSelectItem($child);
        }

        return $items;
    }

    /**
     * @return array{type: 'column'|'wildcard', table: string|null, name?: string, alias: string|null}
     */
    private function normalizeSelectItem(PrintableNode $item): array
    {
        $wildcard = $this->findFirstChildNode($item, 'WildcardSelection');
        if ($wildcard instanceof PrintableNode) {
            return $this->normalizeWildcardSelection($wildcard);
        }

        $column = $this->findFirstChildNode($item, 'AliasedColumn');
        self::assertInstanceOf(PrintableNode::class, $column);

        $columnRef = $this->findFirstChildNode($column, 'ColumnRef');
        self::assertInstanceOf(PrintableNode::class, $columnRef);

        $reference = $this->normalizeColumnRef($columnRef);
        $directTokens = [];

        foreach ($column->children as $child) {
            if ($child instanceof TokenInterface) {
                $directTokens[] = $child->getValue();
            }
        }

        $alias = $directTokens === [] ? null : $directTokens[array_key_last($directTokens)];
        if ($alias === 'as') {
            $alias = null;
        }

        return [
            'type' => 'column',
            'table' => $reference['table'],
            'name' => $reference['name'],
            'alias' => $alias,
        ];
    }

    /**
     * @return array{type: 'wildcard', table: string|null, alias: null}
     */
    private function normalizeWildcardSelection(PrintableNode $wildcard): array
    {
        $tokens = $this->tokens($wildcard);

        return [
            'type' => 'wildcard',
            'table' => count($tokens) === 3 ? $tokens[0] : null,
            'alias' => null,
        ];
    }

    /**
     * @return array{table: string, alias: string|null}
     */
    private function normalizeTableRef(PrintableNode $tableRef): array
    {
        $tokens = $this->tokens($tableRef);
        self::assertNotEmpty($tokens);
        $lastToken = $tokens[count($tokens) - 1];

        return [
            'table' => $tokens[0],
            'alias' => $lastToken !== $tokens[0] ? $lastToken : null,
        ];
    }

    /**
     * @return array{
     *     type: string|null,
     *     table: array{table: string, alias: string|null},
     *     on: array{
     *         operator: string,
     *         left: array{type: 'column'|'placeholder', table?: string|null, name: string},
     *         right: array{type: 'column'|'placeholder', table?: string|null, name: string}
     *     }
     * }
     */
    private function normalizeJoinClause(PrintableNode $join): array
    {
        $joinTypeNode = $this->findFirstChildNode($join, 'JoinType');
        $tableNode = $this->findNthChildNode($join, 'TableRef', 0);
        $comparisonNode = $this->findFirstChildNode($join, 'ComparisonExpr');

        self::assertInstanceOf(PrintableNode::class, $tableNode);
        self::assertInstanceOf(PrintableNode::class, $comparisonNode);

        return [
            'type' => $joinTypeNode instanceof PrintableNode ? strtolower($this->firstTokenValue($joinTypeNode)) : null,
            'table' => $this->normalizeTableRef($tableNode),
            'on' => $this->normalizeComparison($comparisonNode),
        ];
    }

    /**
     * @return array{
     *     0: list<array{
     *         operator: string,
     *         left: array{type: 'column'|'placeholder', table?: string|null, name: string},
     *         right: array{type: 'column'|'placeholder', table?: string|null, name: string}
     *     }>,
     *     1: list<string>
     * }
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

    /**
     * @return array{
     *     operator: string,
     *     left: array{type: 'column'|'placeholder', table?: string|null, name: string},
     *     right: array{type: 'column'|'placeholder', table?: string|null, name: string}
     * }
     */
    private function normalizeComparison(PrintableNode $comparison): array
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

        self::assertCount(2, $operands);
        self::assertIsString($operator);

        return [
            'operator' => $operator,
            'left' => $operands[0],
            'right' => $operands[1],
        ];
    }

    /**
     * @return array{type: 'column'|'placeholder', table?: string|null, name: string}
     */
    private function normalizeOperand(PrintableNode $operand): array
    {
        $column = $this->findFirstChildNode($operand, 'ColumnRef');
        if ($column instanceof PrintableNode) {
            $reference = $this->normalizeColumnRef($column);

            return [
                'type' => 'column',
                'table' => $reference['table'],
                'name' => $reference['name'],
            ];
        }

        $placeholder = $this->findFirstChildNode($operand, 'Placeholder');
        self::assertInstanceOf(PrintableNode::class, $placeholder);

        return [
            'type' => 'placeholder',
            'name' => $this->tokens($placeholder)[1],
        ];
    }

    /**
     * @return array{table: string|null, name: string}
     */
    private function normalizeColumnRef(PrintableNode $columnRef): array
    {
        $tokens = $this->tokens($columnRef);

        if (count($tokens) === 3) {
            return [
                'table' => $tokens[0],
                'name' => $tokens[2],
            ];
        }

        return [
            'table' => null,
            'name' => $tokens[0],
        ];
    }

    private function firstTokenValue(PrintableNode $node): string
    {
        $tokens = $this->tokens($node);
        self::assertNotEmpty($tokens);

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

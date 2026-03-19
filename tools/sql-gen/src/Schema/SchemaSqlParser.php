<?php

declare(strict_types=1);

namespace SqlGen\Schema;

final class SchemaSqlParser
{
    private const TABLE_CONSTRAINT_KEYWORDS = [
        'PRIMARY',
        'UNIQUE',
        'CONSTRAINT',
        'FOREIGN',
        'CHECK',
    ];

    /**
     * @return list<SchemaTableDefinition>
     */
    public function parse(string $sql): array
    {
        $tokens = (new SchemaTokenizer())->tokenize($sql);
        $tables = [];
        $offset = 0;
        $count = count($tokens);

        while ($offset < $count) {
            if ($this->matchesWords($tokens, $offset, ['CREATE', 'TABLE'])) {
                $tables[] = $this->parseCreateTable($tokens, $offset);
                continue;
            }

            $offset = $this->skipStatement($tokens, $offset);
        }

        return $tables;
    }

    /**
     * @param list<SchemaToken> $tokens
     */
    private function parseCreateTable(array $tokens, int &$offset): SchemaTableDefinition
    {
        $offset += 2;
        $tableName = $this->expectWord($tokens, $offset, 'table name');
        $this->expectToken($tokens, $offset, '(', 'table opening parenthesis');

        $elements = [];
        $currentElement = [];
        $depth = 1;
        $count = count($tokens);

        while ($offset < $count) {
            $token = $tokens[$offset];
            $offset++;

            if ($token->type === '(') {
                $depth++;
                $currentElement[] = $token;
                continue;
            }

            if ($token->type === ')') {
                $depth--;

                if ($depth === 0) {
                    if ($currentElement !== []) {
                        $elements[] = $currentElement;
                    }

                    break;
                }

                $currentElement[] = $token;
                continue;
            }

            if ($token->type === ',' && $depth === 1) {
                if ($currentElement !== []) {
                    $elements[] = $currentElement;
                    $currentElement = [];
                }

                continue;
            }

            $currentElement[] = $token;
        }

        if ($depth !== 0) {
            throw new \RuntimeException("Unclosed CREATE TABLE definition for {$tableName}");
        }

        $this->consumeOptionalSemicolon($tokens, $offset);

        $columns = [];

        foreach ($elements as $elementTokens) {
            $column = $this->parseColumnDefinition($elementTokens);
            if ($column === null) {
                continue;
            }

            $columns[] = $column;
        }

        return new SchemaTableDefinition($tableName, $columns);
    }

    /**
     * @param list<SchemaToken> $tokens
     */
    private function skipStatement(array $tokens, int $offset): int
    {
        $count = count($tokens);

        while ($offset < $count) {
            if ($tokens[$offset]->type === ';') {
                return $offset + 1;
            }

            $offset++;
        }

        return $offset;
    }

    /**
     * @param list<SchemaToken> $tokens
     */
    private function expectWord(array $tokens, int &$offset, string $context): string
    {
        $token = $tokens[$offset] ?? null;
        if (!$token instanceof SchemaToken || $token->type !== 'word') {
            throw new \RuntimeException("Expected {$context}");
        }

        $offset++;

        return $token->value;
    }

    /**
     * @param list<SchemaToken> $tokens
     */
    private function expectToken(array $tokens, int &$offset, string $type, string $context): void
    {
        $token = $tokens[$offset] ?? null;
        if (!$token instanceof SchemaToken || $token->type !== $type) {
            throw new \RuntimeException("Expected {$context}");
        }

        $offset++;
    }

    /**
     * @param list<SchemaToken> $tokens
     */
    private function consumeOptionalSemicolon(array $tokens, int &$offset): void
    {
        $token = $tokens[$offset] ?? null;
        if ($token instanceof SchemaToken && $token->type === ';') {
            $offset++;
        }
    }

    /**
     * @param list<SchemaToken> $tokens
     * @param list<string> $words
     */
    private function matchesWords(array $tokens, int $offset, array $words): bool
    {
        foreach ($words as $index => $word) {
            $token = $tokens[$offset + $index] ?? null;
            if (!$token instanceof SchemaToken || $token->type !== 'word' || strtoupper($token->value) !== $word) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<SchemaToken> $tokens
     */
    private function parseColumnDefinition(array $tokens): ?SchemaColumnDefinition
    {
        $tokenSequence = new SchemaTokenSequence($tokens);
        $firstToken = $tokenSequence->first();

        if ($firstToken->type !== 'word') {
            throw new \RuntimeException('Unsupported table element in schema SQL');
        }

        if (in_array(strtoupper($firstToken->value), self::TABLE_CONSTRAINT_KEYWORDS, true)) {
            return null;
        }

        $name = $firstToken->value;
        $typeToken = $tokenSequence->second();

        if ($typeToken->type !== 'word') {
            throw new \RuntimeException("Unsupported column definition in schema SQL: {$name}");
        }

        $nullable = true;
        $count = count($tokens);

        for ($offset = 2; $offset < $count; $offset++) {
            $token = $tokens[$offset];
            if ($token->type !== 'word') {
                continue;
            }

            $upper = strtoupper($token->value);

            if ($upper === 'NOT') {
                $next = $tokenSequence->next($offset);
                if ($next instanceof SchemaToken && $next->type === 'word' && strtoupper($next->value) === 'NULL') {
                    $nullable = false;
                }
            }

            if ($upper === 'PRIMARY') {
                $next = $tokenSequence->next($offset);
                if ($next instanceof SchemaToken && $next->type === 'word' && strtoupper($next->value) === 'KEY') {
                    $nullable = false;
                }
            }
        }

        return new SchemaColumnDefinition(
            name: $name,
            sqlType: strtoupper($typeToken->value),
            nullable: $nullable,
        );
    }
}

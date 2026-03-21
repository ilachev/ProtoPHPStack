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
        $primaryKeyColumns = [];
        $uniqueConstraints = [];
        $foreignKeys = [];

        foreach ($elements as $elementTokens) {
            $column = $this->parseColumnDefinition($elementTokens);
            if ($column === null) {
                $this->collectTableConstraint(
                    $elementTokens,
                    $primaryKeyColumns,
                    $uniqueConstraints,
                    $foreignKeys,
                );
            } else {
                $columns[] = $column;
            }
        }

        return new SchemaTableDefinition(
            name: $tableName,
            columns: $columns,
            primaryKeyColumns: $primaryKeyColumns,
            uniqueConstraints: $uniqueConstraints,
            foreignKeys: $foreignKeys,
        );
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
        $primaryKey = false;
        $unique = false;
        $reference = null;
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
                    $primaryKey = true;
                    $nullable = false;
                }
            }

            if ($upper === 'UNIQUE') {
                $unique = true;
            }

            if ($upper === 'REFERENCES') {
                $reference = $this->parseInlineReference($tokens, $offset, $name);
            }
        }

        return new SchemaColumnDefinition(
            name: $name,
            sqlType: strtoupper($typeToken->value),
            nullable: $nullable,
            primaryKey: $primaryKey,
            unique: $unique,
            reference: $reference,
        );
    }

    /**
     * @param list<SchemaToken> $tokens
     * @param list<string> $primaryKeyColumns
     * @param list<SchemaUniqueConstraintDefinition> $uniqueConstraints
     * @param list<SchemaForeignKeyConstraintDefinition> $foreignKeys
     */
    private function collectTableConstraint(
        array $tokens,
        array &$primaryKeyColumns,
        array &$uniqueConstraints,
        array &$foreignKeys,
    ): void {
        $normalizedTokens = $this->stripConstraintPrefix($tokens);
        if ($normalizedTokens === []) {
            return;
        }

        if ($this->matchesLeadingWords($normalizedTokens, ['PRIMARY', 'KEY'])) {
            $primaryKeyColumns = $this->parseParenthesizedIdentList($normalizedTokens, 2, 'PRIMARY KEY');
            return;
        }

        if ($this->matchesLeadingWords($normalizedTokens, ['UNIQUE'])) {
            $uniqueConstraints[] = new SchemaUniqueConstraintDefinition(
                $this->parseParenthesizedIdentList($normalizedTokens, 1, 'UNIQUE'),
            );
            return;
        }

        if ($this->matchesLeadingWords($normalizedTokens, ['FOREIGN', 'KEY'])) {
            $columns = $this->parseParenthesizedIdentList($normalizedTokens, 2, 'FOREIGN KEY');
            $referenceOffset = $this->findWordOffset($normalizedTokens, 'REFERENCES');
            if ($referenceOffset === null) {
                throw new \RuntimeException('FOREIGN KEY constraint is missing REFERENCES clause.');
            }

            $referencedTable = $this->expectWordAt($normalizedTokens, $referenceOffset + 1, 'FOREIGN KEY referenced table');
            $referencedColumns = $this->parseParenthesizedIdentList(
                $normalizedTokens,
                $referenceOffset + 2,
                'FOREIGN KEY REFERENCES',
            );

            $foreignKeys[] = new SchemaForeignKeyConstraintDefinition(
                columns: $columns,
                referencedTable: $referencedTable,
                referencedColumns: $referencedColumns,
            );
        }
    }

    /**
     * @param list<SchemaToken> $tokens
     * @return list<SchemaToken>
     */
    private function stripConstraintPrefix(array $tokens): array
    {
        if (!$this->matchesLeadingWords($tokens, ['CONSTRAINT'])) {
            return $tokens;
        }

        return array_slice($tokens, 2);
    }

    /**
     * @param list<SchemaToken> $tokens
     * @param list<string> $words
     */
    private function matchesLeadingWords(array $tokens, array $words): bool
    {
        foreach ($words as $index => $word) {
            $token = $tokens[$index] ?? null;
            if (!$token instanceof SchemaToken || $token->type !== 'word' || strtoupper($token->value) !== $word) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<SchemaToken> $tokens
     */
    private function parseInlineReference(array $tokens, int $referenceOffset, string $columnName): SchemaTableReferenceDefinition
    {
        $referencedTable = $this->expectWordAt($tokens, $referenceOffset + 1, "inline REFERENCES table for {$columnName}");
        $referencedColumns = $this->parseParenthesizedIdentList($tokens, $referenceOffset + 2, "inline REFERENCES for {$columnName}");

        if (count($referencedColumns) !== 1) {
            throw new \RuntimeException("Inline REFERENCES for {$columnName} must target exactly one column.");
        }

        return new SchemaTableReferenceDefinition(
            table: $referencedTable,
            column: $referencedColumns[0],
        );
    }

    /**
     * @param list<SchemaToken> $tokens
     */
    private function expectWordAt(array $tokens, int $offset, string $context): string
    {
        $token = $tokens[$offset] ?? null;
        if (!$token instanceof SchemaToken || $token->type !== 'word') {
            throw new \RuntimeException("Expected {$context}.");
        }

        return $token->value;
    }

    /**
     * @param list<SchemaToken> $tokens
     */
    private function findWordOffset(array $tokens, string $word): ?int
    {
        foreach ($tokens as $offset => $token) {
            if ($token->type === 'word' && strtoupper($token->value) === $word) {
                return $offset;
            }
        }

        return null;
    }

    /**
     * @param list<SchemaToken> $tokens
     * @return list<string>
     */
    private function parseParenthesizedIdentList(array $tokens, int $offset, string $context): array
    {
        $open = $tokens[$offset] ?? null;
        if (!$open instanceof SchemaToken || $open->type !== '(') {
            throw new \RuntimeException("Expected opening parenthesis for {$context}.");
        }

        $identifiers = [];
        $count = count($tokens);
        $offset++;

        while ($offset < $count) {
            $token = $tokens[$offset];
            if ($token->type === ')') {
                if ($identifiers === []) {
                    throw new \RuntimeException("Expected identifiers in {$context}.");
                }

                return $identifiers;
            }

            if ($token->type === ',') {
                $offset++;
                continue;
            }

            if ($token->type !== 'word') {
                throw new \RuntimeException("Unexpected token in {$context}.");
            }

            $identifiers[] = $token->value;
            $offset++;
        }

        throw new \RuntimeException("Unclosed identifier list in {$context}.");
    }
}

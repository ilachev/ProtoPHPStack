<?php

declare(strict_types=1);

namespace SqlGen\Schema;

use Phplrt\Compiler\Compiler;
use Phplrt\Compiler\Runtime\PrintableNode;
use Phplrt\Contracts\Lexer\TokenInterface;

final class SchemaSqlParser
{
    private readonly Compiler $compiler;

    public function __construct()
    {
        $grammar = file_get_contents(__DIR__ . '/grammar/schema_subset.pp2');
        if (!is_string($grammar)) {
            throw new \RuntimeException('Unable to load phplrt schema subset grammar.');
        }

        $this->compiler = new Compiler();
        $this->compiler->load($grammar);
    }

    /**
     * @return list<SchemaTableDefinition>
     */
    public function parse(string $sql): array
    {
        $tables = [];

        foreach ($this->extractCreateTableStatements($sql) as $statement) {
            /** @var PrintableNode $parsed */
            $parsed = $this->compiler->parse($statement);
            $createTable = $parsed->getState() === 'CreateTableStmt'
                ? $parsed
                : $this->findFirstChildNode($parsed, 'CreateTableStmt');
            if (!$createTable instanceof PrintableNode) {
                throw new \RuntimeException('Schema subset parser did not produce CreateTableStmt.');
            }

            $tables[] = $this->normalizeCreateTable($createTable);
        }

        return $tables;
    }

    private function normalizeCreateTable(PrintableNode $createTable): SchemaTableDefinition
    {
        $tableName = $this->findDirectTokenValueAfter($createTable, 'T_TABLE', 'T_IDENT');
        $elementsNode = $this->findFirstChildNode($createTable, 'TableElements');

        if ($tableName === null || !$elementsNode instanceof PrintableNode) {
            throw new \RuntimeException('CreateTableStmt must contain table name and table elements.');
        }

        $columns = [];
        $primaryKeyColumns = [];
        $uniqueConstraints = [];
        $foreignKeys = [];

        foreach ($elementsNode->children as $child) {
            if (!$child instanceof PrintableNode || $child->getState() !== 'TableElement') {
                continue;
            }

            $columnDefinition = $this->findFirstChildNode($child, 'ColumnDef');
            if ($columnDefinition instanceof PrintableNode) {
                $columns[] = $this->normalizeColumnDefinition($columnDefinition);
                continue;
            }

            $constraint = $this->findFirstChildNode($child, 'TableConstraint');
            if (!$constraint instanceof PrintableNode) {
                throw new \RuntimeException("Unsupported CREATE TABLE element in {$tableName}");
            }

            [$tablePrimaryKeys, $tableUniqueConstraints, $tableForeignKeys] = $this->normalizeTableConstraint($constraint);
            array_push($primaryKeyColumns, ...$tablePrimaryKeys);
            array_push($uniqueConstraints, ...$tableUniqueConstraints);
            array_push($foreignKeys, ...$tableForeignKeys);
        }

        return new SchemaTableDefinition(
            name: $tableName,
            columns: $columns,
            primaryKeyColumns: $this->uniqueIdentifiers($primaryKeyColumns),
            uniqueConstraints: $this->uniqueConstraintDefinitions($uniqueConstraints),
            foreignKeys: $this->uniqueForeignKeyDefinitions($foreignKeys),
        );
    }

    private function normalizeColumnDefinition(PrintableNode $columnDefinition): SchemaColumnDefinition
    {
        $identifiers = $this->directTokenValuesByName($columnDefinition, 'T_IDENT');
        if (count($identifiers) < 2) {
            throw new \RuntimeException('ColumnDef must contain column name and type.');
        }

        [$name, $sqlType] = $identifiers;
        $nullable = true;
        $primaryKey = false;
        $unique = false;
        $reference = null;

        foreach ($columnDefinition->children as $child) {
            if (!$child instanceof PrintableNode) {
                continue;
            }

            $constraint = $child->getState() === 'ColumnConstraint'
                ? $child
                : $this->findFirstChildNode($child, 'ColumnConstraint');
            if (!$constraint instanceof PrintableNode) {
                continue;
            }

            if ($this->findFirstChildNode($constraint, 'NotNullConstraint') instanceof PrintableNode) {
                $nullable = false;
                continue;
            }

            if ($this->findFirstChildNode($constraint, 'PrimaryKeyConstraint') instanceof PrintableNode) {
                $primaryKey = true;
                $nullable = false;
                continue;
            }

            if ($this->findFirstChildNode($constraint, 'UniqueConstraint') instanceof PrintableNode) {
                $unique = true;
                continue;
            }

            $inlineReference = $this->findFirstChildNode($constraint, 'InlineReferencesConstraint');
            if ($inlineReference instanceof PrintableNode) {
                $reference = $this->normalizeInlineReference($inlineReference, $name);
            }
        }

        return new SchemaColumnDefinition(
            name: $name,
            sqlType: strtoupper($sqlType),
            nullable: $nullable,
            primaryKey: $primaryKey,
            unique: $unique,
            reference: $reference,
        );
    }

    /**
     * @return array{
     *     0: list<string>,
     *     1: list<SchemaUniqueConstraintDefinition>,
     *     2: list<SchemaForeignKeyConstraintDefinition>
     * }
     */
    private function normalizeTableConstraint(PrintableNode $tableConstraint): array
    {
        $primaryKeys = [];
        $uniqueConstraints = [];
        $foreignKeys = [];

        foreach ($tableConstraint->children as $child) {
            if (!$child instanceof PrintableNode) {
                continue;
            }

            if ($child->getState() === 'TablePrimaryKeyConstraint') {
                $identList = $this->findFirstChildNode($child, 'IdentList');
                if (!$identList instanceof PrintableNode) {
                    throw new \RuntimeException('PRIMARY KEY constraint must contain IdentList.');
                }

                array_push($primaryKeys, ...$this->normalizeIdentList($identList));
                continue;
            }

            if ($child->getState() === 'TableUniqueConstraint') {
                $identList = $this->findFirstChildNode($child, 'IdentList');
                if (!$identList instanceof PrintableNode) {
                    throw new \RuntimeException('UNIQUE constraint must contain IdentList.');
                }

                $uniqueConstraints[] = new SchemaUniqueConstraintDefinition(
                    $this->normalizeIdentList($identList),
                );
                continue;
            }

            if ($child->getState() === 'TableForeignKeyConstraint') {
                $foreignKeys[] = $this->normalizeForeignKeyConstraint($child);
            }
        }

        return [$primaryKeys, $uniqueConstraints, $foreignKeys];
    }

    private function normalizeInlineReference(
        PrintableNode $inlineReference,
        string $columnName,
    ): SchemaTableReferenceDefinition {
        $referencedTable = $this->findDirectTokenValueAfter($inlineReference, 'T_REFERENCES', 'T_IDENT');
        $identList = $this->findFirstChildNode($inlineReference, 'IdentList');

        if (!is_string($referencedTable) || !$identList instanceof PrintableNode) {
            throw new \RuntimeException("Inline REFERENCES for {$columnName} must contain target table and column.");
        }

        $referencedColumns = $this->normalizeIdentList($identList);
        if (count($referencedColumns) !== 1) {
            throw new \RuntimeException("Inline REFERENCES for {$columnName} must target exactly one column.");
        }

        return new SchemaTableReferenceDefinition(
            table: $referencedTable,
            column: $referencedColumns[0],
        );
    }

    private function normalizeForeignKeyConstraint(
        PrintableNode $foreignKeyConstraint,
    ): SchemaForeignKeyConstraintDefinition {
        $identLists = [];

        foreach ($foreignKeyConstraint->children as $child) {
            if ($child instanceof PrintableNode && $child->getState() === 'IdentList') {
                $identLists[] = $this->normalizeIdentList($child);
            }
        }

        $identifiers = $this->directTokenValuesByName($foreignKeyConstraint, 'T_IDENT');

        if (count($identLists) !== 2 || count($identifiers) < 1) {
            throw new \RuntimeException('FOREIGN KEY constraint must contain local columns, referenced table and referenced columns.');
        }

        return new SchemaForeignKeyConstraintDefinition(
            columns: $identLists[0],
            referencedTable: $identifiers[0],
            referencedColumns: $identLists[1],
        );
    }

    /**
     * @return list<string>
     */
    private function normalizeIdentList(PrintableNode $identList): array
    {
        return $this->directTokenValuesByName($identList, 'T_IDENT');
    }

    /**
     * @return list<string>
     */
    private function extractCreateTableStatements(string $sql): array
    {
        $statements = [];
        $length = strlen($sql);
        $offset = 0;

        while ($offset < $length) {
            $statementStart = $this->findNextCreateTable($sql, $offset);
            if ($statementStart === null) {
                break;
            }

            $statementEnd = $this->findStatementEnd($sql, $statementStart);
            $statement = trim(substr($sql, $statementStart, $statementEnd - $statementStart));
            if ($statement !== '') {
                $statements[] = $statement;
            }

            $offset = $statementEnd;
        }

        return $statements;
    }

    private function findNextCreateTable(string $sql, int $offset): ?int
    {
        $length = strlen($sql);

        while ($offset < $length) {
            if ($this->startsWithCreateTable($sql, $offset)) {
                return $offset;
            }

            $character = $sql[$offset];
            if ($character === "'" || $character === '"') {
                $offset = $this->skipQuotedLiteral($sql, $offset, $character, $length);
                continue;
            }

            if ($character === '-' && ($sql[$offset + 1] ?? null) === '-') {
                $offset = $this->skipLineComment($sql, $offset + 2, $length);
                continue;
            }

            $offset++;
        }

        return null;
    }

    private function startsWithCreateTable(string $sql, int $offset): bool
    {
        $prefix = substr($sql, $offset, 12);
        if (strtoupper($prefix) !== 'CREATE TABLE') {
            return false;
        }

        $before = $offset > 0 ? $sql[$offset - 1] : null;
        $after = $sql[$offset + 12] ?? null;

        return !$this->isIdentifierCharacter($before) && !$this->isIdentifierCharacter($after);
    }

    private function findStatementEnd(string $sql, int $offset): int
    {
        $length = strlen($sql);
        $depth = 0;

        while ($offset < $length) {
            $character = $sql[$offset];

            if ($character === "'" || $character === '"') {
                $offset = $this->skipQuotedLiteral($sql, $offset, $character, $length);
                continue;
            }

            if ($character === '-' && ($sql[$offset + 1] ?? null) === '-') {
                $offset = $this->skipLineComment($sql, $offset + 2, $length);
                continue;
            }

            if ($character === '(') {
                $depth++;
                $offset++;
                continue;
            }

            if ($character === ')') {
                $depth--;
                $offset++;
                continue;
            }

            if ($character === ';' && $depth === 0) {
                return $offset + 1;
            }

            $offset++;
        }

        return $offset;
    }

    private function skipQuotedLiteral(string $sql, int $offset, string $quote, int $length): int
    {
        $offset++;

        while ($offset < $length) {
            $character = $sql[$offset];

            if ($character === $quote) {
                if ($quote === "'" && ($sql[$offset + 1] ?? null) === "'") {
                    $offset += 2;
                    continue;
                }

                return $offset + 1;
            }

            $offset++;
        }

        return $offset;
    }

    private function skipLineComment(string $sql, int $offset, int $length): int
    {
        while ($offset < $length && $sql[$offset] !== "\n") {
            $offset++;
        }

        return $offset;
    }

    private function isIdentifierCharacter(?string $character): bool
    {
        return is_string($character) && preg_match('/[a-zA-Z0-9_]/', $character) === 1;
    }

    /**
     * @return list<string>
     */
    private function directTokenValuesByName(PrintableNode $node, string $tokenName): array
    {
        $values = [];

        foreach ($node->children as $child) {
            if ($child instanceof TokenInterface && $child->getName() === $tokenName) {
                $values[] = $child->getValue();
            }
        }

        return $values;
    }

    private function findDirectTokenValueAfter(PrintableNode $node, string $afterTokenName, string $targetTokenName): ?string
    {
        $seenAfter = false;

        foreach ($node->children as $child) {
            if (!$child instanceof TokenInterface) {
                continue;
            }

            if ($child->getName() === $afterTokenName) {
                $seenAfter = true;
                continue;
            }

            if ($seenAfter && $child->getName() === $targetTokenName) {
                return $child->getValue();
            }
        }

        return null;
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

    /**
     * @param list<string> $identifiers
     * @return list<string>
     */
    private function uniqueIdentifiers(array $identifiers): array
    {
        return array_values(array_unique($identifiers));
    }

    /**
     * @param list<SchemaUniqueConstraintDefinition> $constraints
     * @return list<SchemaUniqueConstraintDefinition>
     */
    private function uniqueConstraintDefinitions(array $constraints): array
    {
        $uniqueByKey = [];

        foreach ($constraints as $constraint) {
            $key = implode('|', $constraint->columns);
            $uniqueByKey[$key] = $constraint;
        }

        return array_values($uniqueByKey);
    }

    /**
     * @param list<SchemaForeignKeyConstraintDefinition> $foreignKeys
     * @return list<SchemaForeignKeyConstraintDefinition>
     */
    private function uniqueForeignKeyDefinitions(array $foreignKeys): array
    {
        $foreignKeysByKey = [];

        foreach ($foreignKeys as $foreignKey) {
            $key = implode('|', $foreignKey->columns)
                . '=>'
                . $foreignKey->referencedTable
                . '(' . implode('|', $foreignKey->referencedColumns) . ')';
            $foreignKeysByKey[$key] = $foreignKey;
        }

        return array_values($foreignKeysByKey);
    }
}

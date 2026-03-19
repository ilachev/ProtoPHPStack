<?php

declare(strict_types=1);

namespace Tests\Unit\Parser;

use PHPUnit\Framework\TestCase;
use SqlGen\Parser\ColumnReferenceTokens;
use SqlGen\Parser\ExcludedReferenceTokens;
use SqlGen\Parser\PlaceholderTokens;
use SqlGen\Parser\SelectAliasTokens;
use SqlGen\Parser\TableReferenceTokens;
use SqlGen\Parser\WildcardSelectionTokens;

final class NodeTokenReadersTest extends TestCase
{
    public function testParsesQualifiedColumnReferenceTokens(): void
    {
        $reference = (new ColumnReferenceTokens(['sessions', '.', 'id']))->toColumnReference();

        self::assertSame('sessions', $reference->table);
        self::assertSame('id', $reference->column);
    }

    public function testParsesTableReferenceTokensWithAsAlias(): void
    {
        $reference = (new TableReferenceTokens(['sessions', 'as', 's']))->toTableReference();

        self::assertSame('sessions', $reference->table);
        self::assertSame('s', $reference->alias);
    }

    public function testParsesNamedPlaceholderTokens(): void
    {
        $placeholder = (new PlaceholderTokens([':', 'user_id']))->toPlaceholder();

        self::assertSame('user_id', $placeholder->name);
    }

    public function testParsesQualifiedWildcardTokens(): void
    {
        $wildcard = (new WildcardSelectionTokens(['sessions', '.', '*']))->toProjectionWildcard();

        self::assertSame('sessions', $wildcard->table);
    }

    public function testParsesSelectAliasTokens(): void
    {
        $alias = (new SelectAliasTokens(['as', 'session_id']))->toAlias();

        self::assertNotNull($alias);
        self::assertSame('session_id', $alias->value);
    }

    public function testParsesExcludedReferenceTokens(): void
    {
        $column = (new ExcludedReferenceTokens(['EXCLUDED', '.', 'payload']))->column();

        self::assertSame('payload', $column);
    }
}

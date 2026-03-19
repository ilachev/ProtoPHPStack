<?php

declare(strict_types=1);

namespace SqlGen\Schema;

final class SchemaTokenizer
{
    /**
     * @return list<SchemaToken>
     */
    public function tokenize(string $sql): array
    {
        $tokens = [];
        $length = strlen($sql);
        $offset = 0;

        while ($offset < $length) {
            $character = $sql[$offset];

            if (ctype_space($character)) {
                $offset++;
                continue;
            }

            if ($character === '-' && ($sql[$offset + 1] ?? null) === '-') {
                $offset = $this->skipLineComment($sql, $offset + 2, $length);
                continue;
            }

            if (ctype_alpha($character) || $character === '_') {
                $start = $offset;
                $offset++;

                while ($offset < $length) {
                    $next = $sql[$offset];
                    if (!ctype_alnum($next) && $next !== '_') {
                        break;
                    }

                    $offset++;
                }

                $value = substr($sql, $start, $offset - $start);
                $tokens[] = new SchemaToken('word', $value);
                continue;
            }

            if (in_array($character, ['(', ')', ',', ';'], true)) {
                $tokens[] = new SchemaToken($character, $character);
                $offset++;
                continue;
            }

            $tokens[] = new SchemaToken('symbol', $character);
            $offset++;
        }

        return $tokens;
    }

    private function skipLineComment(string $sql, int $offset, int $length): int
    {
        while ($offset < $length && $sql[$offset] !== "\n") {
            $offset++;
        }

        return $offset;
    }
}

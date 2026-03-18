<?php

declare(strict_types=1);

namespace SqlGen\Parser;

use SqlGen\Model\SqlFile;
use SqlGen\Model\SqlParameter;
use SqlGen\Model\SqlResultKind;
use SqlGen\Model\SqlStatement;

final class SqlFileParser
{
    private const HEADER_PATTERN = '/^--\s*name:\s*(?<name>[A-Za-z][A-Za-z0-9_]*)\s*:(?<kind>one|many|exec)\s*$/m';
    private const PARAM_PATTERN = '/(?<!:):(?<name>[A-Za-z_][A-Za-z0-9_]*)/';

    public function parseFile(string $path): SqlFile
    {
        $contents = file_get_contents($path);
        if (!is_string($contents)) {
            throw new \RuntimeException("Unable to read SQL file: {$path}");
        }

        preg_match_all(self::HEADER_PATTERN, $contents, $matches, PREG_OFFSET_CAPTURE);

        $names = $matches['name'];
        $kinds = $matches['kind'];
        $fullMatches = $matches[0];

        if ($names === [] || $kinds === [] || $fullMatches === []) {
            throw new \RuntimeException("No named SQL statements found in: {$path}");
        }

        $statements = [];

        foreach ($fullMatches as $index => $fullMatch) {
            $header = $fullMatch[0];
            $offset = $fullMatch[1];
            $name = $names[$index][0];
            $kind = $kinds[$index][0];

            $start = $offset + strlen($header);
            $end = isset($fullMatches[$index + 1][1])
                ? $fullMatches[$index + 1][1]
                : strlen($contents);

            $sql = trim(substr($contents, $start, $end - $start));
            if ($sql === '') {
                throw new \RuntimeException("SQL statement {$name} in {$path} is empty");
            }

            $statements[] = new SqlStatement(
                name: $name,
                resultKind: SqlResultKind::fromDeclaration($kind),
                sql: $sql,
                parameters: $this->extractParameters($sql),
            );
        }

        return new SqlFile(
            sourcePath: $path,
            moduleName: $this->buildModuleName($path),
            statements: $statements,
        );
    }

    /**
     * @return list<SqlParameter>
     */
    private function extractParameters(string $sql): array
    {
        preg_match_all(self::PARAM_PATTERN, $sql, $matches);

        $names = $matches['name'];
        $uniqueNames = [];

        foreach ($names as $name) {
            $uniqueNames[$name] = true;
        }

        return array_map(
            static fn(string $name): SqlParameter => new SqlParameter($name),
            array_keys($uniqueNames),
        );
    }

    private function buildModuleName(string $path): string
    {
        $basename = pathinfo($path, PATHINFO_FILENAME);
        $normalized = preg_replace('/[^A-Za-z0-9]+/', ' ', $basename);

        if (!is_string($normalized) || $normalized === '') {
            throw new \RuntimeException("Unable to build SQL module name for: {$path}");
        }

        return str_replace(' ', '', ucwords($normalized));
    }
}

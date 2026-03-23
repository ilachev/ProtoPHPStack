<?php

declare(strict_types=1);

namespace SqlGen\Config;

readonly class SqlArtifactNaming
{
    public function __construct(
        public string $queryClassSuffix = 'Query',
        public string $rowClassSuffix = 'Row',
    ) {}

    public function moduleNameFromPath(string $path): string
    {
        $basename = pathinfo($path, PATHINFO_FILENAME);
        $words = [];
        $buffer = '';
        $length = strlen($basename);

        for ($offset = 0; $offset < $length; $offset++) {
            $character = $basename[$offset];

            if (ctype_alnum($character)) {
                $buffer .= $character;
                continue;
            }

            if ($buffer !== '') {
                $words[] = $buffer;
                $buffer = '';
            }
        }

        if ($buffer !== '') {
            $words[] = $buffer;
        }

        if ($words === []) {
            throw new \RuntimeException("Unable to build SQL module name for: {$path}");
        }

        return implode('', array_map(
            static fn(string $word): string => ucfirst(strtolower($word)),
            $words,
        ));
    }

    public function moduleNamespace(string $baseNamespace, string $moduleName): string
    {
        return rtrim($baseNamespace, '\\') . '\\' . $moduleName;
    }

    public function moduleOutputDirectory(string $baseOutputDir, string $moduleName): string
    {
        return rtrim($baseOutputDir, '/') . '/' . $moduleName;
    }

    public function queryClassName(string $statementName): string
    {
        return $statementName . $this->queryClassSuffix;
    }

    public function rowClassName(string $statementName): string
    {
        return $statementName . $this->rowClassSuffix;
    }

    public function sharedRowClassName(string $moduleName, int $groupIndex): string
    {
        return $groupIndex === 1
            ? $moduleName . $this->rowClassSuffix
            : $moduleName . $this->rowClassSuffix . $groupIndex;
    }
}

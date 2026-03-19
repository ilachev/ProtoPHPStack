<?php

declare(strict_types=1);

namespace SqlGen\Application;

use SqlGen\Config\GeneratorConfig;
use SqlGen\Generator\GeneratedFile;
use SqlGen\Generator\PhpQueryGenerator;
use SqlGen\Parser\SqlFileParser;
use SqlGen\Schema\SqlSchemaParser;

final readonly class SqlGenerationService
{
    public function __construct(
        private SqlFileParser $sqlFileParser = new SqlFileParser(),
        private SqlSchemaParser $sqlSchemaParser = new SqlSchemaParser(),
    ) {}

    /**
     * @return list<GeneratedFile>
     */
    public function generate(GeneratorConfig $config): array
    {
        $schema = $this->sqlSchemaParser->parseFile($config->schemaPath);
        $generator = new PhpQueryGenerator($config, $schema);
        $files = glob(rtrim($config->inputDir, '/') . '/*.sql');
        if (!is_array($files)) {
            throw new \RuntimeException("Failed to list SQL files in {$config->inputDir}");
        }

        sort($files);

        $generatedFiles = [];

        foreach ($files as $file) {
            if (!is_string($file) || !is_file($file)) {
                continue;
            }

            $sqlFile = $this->sqlFileParser->parseFile($file);

            foreach ($generator->generateForSqlFile($sqlFile) as $generatedFile) {
                $generatedFiles[] = $generatedFile;
            }
        }

        usort(
            $generatedFiles,
            static fn(GeneratedFile $left, GeneratedFile $right): int => $left->path <=> $right->path,
        );

        return $generatedFiles;
    }
}

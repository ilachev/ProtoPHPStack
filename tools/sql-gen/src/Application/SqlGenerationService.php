<?php

declare(strict_types=1);

namespace SqlGen\Application;

use SqlGen\Config\GeneratorConfig;
use SqlGen\Generator\GeneratedFile;
use SqlGen\Generator\PhpQueryGenerator;
use SqlGen\Parser\SqlFileParser;
use SqlGen\Schema\DatabaseSchemaParser;
use SqlGen\Schema\SqlSchemaParser;

final readonly class SqlGenerationService
{
    public function __construct(
        private DatabaseSchemaParser $sqlSchemaParser = new SqlSchemaParser(),
    ) {}

    /**
     * @return list<GeneratedFile>
     */
    public function generate(GeneratorConfig $config): array
    {
        $schema = $this->sqlSchemaParser->parseFile($config->schemaPath);
        $generator = new PhpQueryGenerator($config, $schema);
        $sqlFileParser = new SqlFileParser($config->profile->artifactNaming);
        $files = glob(rtrim($config->inputDir, '/') . '/*.sql');
        if (!is_array($files)) {
            throw new \RuntimeException("Failed to list SQL files in {$config->inputDir}");
        }

        sort($files);

        $generatedFiles = [];

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $sqlFile = $sqlFileParser->parseFile($file);

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

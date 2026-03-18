#!/usr/bin/env php
<?php

declare(strict_types=1);

use SqlGen\Config\GeneratorConfig;
use SqlGen\Generator\PhpQueryGenerator;
use SqlGen\Parser\SqlFileParser;

require_once __DIR__ . '/../vendor/autoload.php';

$options = getopt('', ['input-dir:', 'output-dir:', 'namespace:']);

if (!is_array($options)) {
    fwrite(STDERR, "Failed to read options.\n");
    exit(1);
}

$inputDir = $options['input-dir'] ?? null;
$outputDir = $options['output-dir'] ?? null;
$namespace = $options['namespace'] ?? null;

if (!is_string($inputDir) || !is_string($outputDir) || !is_string($namespace)) {
    fwrite(
        STDERR,
        "Usage: sql-gen.php --input-dir=sql/queries --output-dir=gen/Generated/Sql --namespace=App\\\\Generated\\\\Sql\n",
    );
    exit(1);
}

$config = new GeneratorConfig(
    inputDir: $inputDir,
    outputDir: $outputDir,
    namespace: $namespace,
);

$parser = new SqlFileParser();
$generator = new PhpQueryGenerator($config);

$files = glob(rtrim($config->inputDir, '/') . '/*.sql');
if (!is_array($files)) {
    fwrite(STDERR, "Failed to list SQL files.\n");
    exit(1);
}

foreach ($files as $file) {
    if (!is_string($file) || !is_file($file)) {
        continue;
    }

    $sqlFile = $parser->parseFile($file);

    foreach ($generator->generateForSqlFile($sqlFile) as $generatedFile) {
        $directory = dirname($generatedFile->path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            fwrite(STDERR, "Failed to create directory: {$directory}\n");
            exit(1);
        }

        file_put_contents($generatedFile->path, $generatedFile->content);
    }
}

#!/usr/bin/env php
<?php

declare(strict_types=1);

use SqlGen\Application\SqlGenerationService;
use SqlGen\Config\GeneratorConfig;

require_once __DIR__ . '/../vendor/autoload.php';

$options = getopt('', ['input-dir:', 'output-dir:', 'namespace:', 'schema:', 'runtime-namespace::']);

if (!is_array($options)) {
    fwrite(STDERR, "Failed to read options.\n");
    exit(1);
}

$inputDir = $options['input-dir'] ?? null;
$outputDir = $options['output-dir'] ?? null;
$namespace = $options['namespace'] ?? null;
$schemaPath = $options['schema'] ?? null;
$runtimeNamespace = $options['runtime-namespace'] ?? 'App\\Platform\\Storage\\Sql';

if (!is_string($inputDir) || !is_string($outputDir) || !is_string($namespace) || !is_string($schemaPath) || !is_string($runtimeNamespace)) {
    fwrite(
        STDERR,
        "Usage: sql-gen.php --input-dir=sql/queries --output-dir=gen/Generated/Sql --namespace=App\\\\Generated\\\\Sql --schema=sql/schema.sql [--runtime-namespace=App\\\\Platform\\\\Storage\\\\Sql]\n",
    );
    exit(1);
}

$config = new GeneratorConfig(
    inputDir: $inputDir,
    outputDir: $outputDir,
    namespace: $namespace,
    schemaPath: $schemaPath,
    runtimeContracts: \SqlGen\Config\SqlRuntimeContracts::fromNamespace($runtimeNamespace),
);

$generationService = new SqlGenerationService();

foreach ($generationService->generate($config) as $generatedFile) {
    $directory = dirname($generatedFile->path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        fwrite(STDERR, "Failed to create directory: {$directory}\n");
        exit(1);
    }

    file_put_contents($generatedFile->path, $generatedFile->content);
}

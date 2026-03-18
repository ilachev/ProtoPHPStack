#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use ProtoPhpGen\Config\StandaloneConfig;
use ProtoPhpGen\Generator\ProtoDomainMapperGenerator;
use ProtoPhpGen\Parser\DomainClassScanner;

// Generator configuration
$sourceDir = __DIR__ . '/../../../src';
$protoDir = __DIR__ . '/../../../protos/proto';
$outputDir = __DIR__ . '/../../../gen/ProtoMapper';
$sourceNamespace = 'App';
$protoNamespace = 'App\Api';
$outputNamespace = 'App\Gen\ProtoMapper';

// Create the output directory if it does not exist yet.
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0o755, true);
}

$config = new StandaloneConfig(
    domainDir: $sourceDir,
    protoDir: $protoDir,
    outputDir: $outputDir,
    domainNamespace: $sourceNamespace,
    protoNamespace: $protoNamespace,
);
$scanner = new DomainClassScanner($config);
$generator = new ProtoDomainMapperGenerator();

echo "Scanning mapped classes in {$sourceDir}\n";

$mappings = $scanner->scan();

// Generate mapper classes for every discovered mapping.
$generatedFiles = 0;
foreach ($mappings as $mapping) {
    try {
        echo "Found mapping for class: {$mapping->getDomainClass()} -> {$mapping->getProtoClass()}\n";

        $outputPath = $generator->generateFromMapping($mapping, $outputDir, $outputNamespace);
        echo "Generated: {$outputPath}\n";
        ++$generatedFiles;
    } catch (Throwable $e) {
        echo "Error processing {$mapping->getDomainClass()}: {$e->getMessage()}\n";
    }
}

echo "Generation completed. Generated {$generatedFiles} files.\n";

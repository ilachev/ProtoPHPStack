#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Generated\Transport\Api\V1\HealthService\CheckHttpHandler;
use App\Platform\Routing\Generator\ProtoRouteProvider;
use App\Platform\Routing\Generator\RoutesWriter;

// Configuration
$metadataDir = __DIR__ . '/../protos/gen/App/Api/V1/Metadata';
$outputFile = __DIR__ . '/../config/routes.php';

// Core template surface keeps explicit mappings only for runtime-native handlers.
$handlerMapping = [
    'HealthService.Check' => CheckHttpHandler::class,
];

// Only app/v1 participates in the default core route surface.
$sourceFilePrefixes = [
    'app/v1/',
];

// Generate routes
$provider = new ProtoRouteProvider($metadataDir, $handlerMapping, $sourceFilePrefixes);
$writer = new RoutesWriter($provider, $outputFile);

try {
    $writer->generateRoutesFile();
    echo "Routes configuration has been successfully generated to {$outputFile}\n";
} catch (Throwable $e) {
    echo "Error generating routes: {$e->getMessage()}\n";
    exit(1);
}

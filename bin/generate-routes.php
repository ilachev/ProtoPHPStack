#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Platform\Routing\Generator\GeneratedRouteManifestProvider;
use App\Platform\Routing\Generator\RoutesWriter;

// Configuration
$manifestDir = __DIR__ . '/../gen/Generated/RouteManifest';
$outputFile = __DIR__ . '/../config/routes.php';

// Generate routes
$provider = new GeneratedRouteManifestProvider($manifestDir);
$writer = new RoutesWriter($provider, $outputFile);

try {
    $writer->generateRoutesFile();
    echo "Routes configuration has been successfully generated to {$outputFile}\n";
} catch (Throwable $e) {
    echo "Error generating routes: {$e->getMessage()}\n";
    exit(1);
}

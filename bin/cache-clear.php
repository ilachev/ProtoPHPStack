#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Cache\CacheService;
use App\Infrastructure\Console\CacheClearCommand;
use App\Infrastructure\Logger\Logger;
use App\Platform\DI\Container;
use App\Platform\DI\DIContainer;

/** @var callable(Container<object>): void $containerConfig */
$containerConfig = require __DIR__ . '/../config/container.php';

$container = new DIContainer();
$containerConfig($container);

$cacheService = $container->get(CacheService::class);
$logger = $container->get(Logger::class);
$command = new CacheClearCommand($cacheService, $logger);

// Execute the command and use its result as the script exit status.
$success = $command->clear();

// Return a non-zero status when cache clearing fails.
exit($success ? 0 : 1);

<?php

declare(strict_types=1);

namespace App\Modules;

use App\Infrastructure\DI\ServiceProvider;

/**
 * Marker interface for feature modules registered by the application bootstrap.
 *
 * @template T of object
 * @extends ServiceProvider<T>
 */
interface Module extends ServiceProvider
{
}

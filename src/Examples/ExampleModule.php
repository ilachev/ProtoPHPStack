<?php

declare(strict_types=1);

namespace App\Examples;

use App\Platform\DI\ServiceProvider;

/**
 * Marker interface for example modules registered on top of platform and capabilities.
 *
 * @template T of object
 * @extends ServiceProvider<T>
 */
interface ExampleModule extends ServiceProvider {}

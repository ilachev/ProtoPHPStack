<?php

declare(strict_types=1);

namespace App\Capabilities;

use App\Platform\DI\ServiceProvider;

/**
 * Marker interface for reusable capabilities registered by the application bootstrap.
 *
 * @template T of object
 * @extends ServiceProvider<T>
 */
interface Capability extends ServiceProvider {}

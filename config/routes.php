<?php

declare(strict_types=1);

use App\Platform\Http\Handler\HandlerInterface;

/**
 * Default infrastructure runtime intentionally exposes no reference routes.
 *
 * @return array<array{
 *     method: string,
 *     path: string,
 *     handler: class-string<HandlerInterface>
 * }>
 */
return [];

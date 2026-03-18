<?php

declare(strict_types=1);

namespace App\Platform\Routing;

final readonly class RouteMapEntry
{
    public function __construct(
        public string $handler,
        public RouteParameters $params,
    ) {}
}

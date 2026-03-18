<?php

declare(strict_types=1);

namespace App\Platform\Routing;

final readonly class RouteEntry
{
    public function __construct(
        public string $method,
        public string $path,
        public string $handler,
        public ?string $operationId = null,
    ) {}
}

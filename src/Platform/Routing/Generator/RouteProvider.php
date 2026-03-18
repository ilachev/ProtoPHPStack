<?php

declare(strict_types=1);

namespace App\Platform\Routing\Generator;

use App\Platform\Routing\RouteEntry;

interface RouteProvider
{
    /**
     * @return list<RouteEntry>
     */
    public function getRoutes(): array;
}

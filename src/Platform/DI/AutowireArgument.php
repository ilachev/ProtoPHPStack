<?php

declare(strict_types=1);

namespace App\Platform\DI;

interface AutowireArgument
{
    /**
     * @phpstan-param DIContainer<object> $container
     */
    public function resolve(DIContainer $container): mixed;
}

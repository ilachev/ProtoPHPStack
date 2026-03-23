<?php

declare(strict_types=1);

namespace App\Platform\DI;

final readonly class DefaultValueArgument implements AutowireArgument
{
    public function __construct(private mixed $value) {}

    /**
     * @phpstan-param DIContainer<object> $container
     */
    public function resolve(DIContainer $container): mixed
    {
        return $this->value;
    }
}

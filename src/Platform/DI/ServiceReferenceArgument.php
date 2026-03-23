<?php

declare(strict_types=1);

namespace App\Platform\DI;

final readonly class ServiceReferenceArgument implements AutowireArgument
{
    /**
     * @param class-string $serviceId
     */
    public function __construct(
        private string $serviceId,
    ) {}

    /**
     * @param DIContainer<object> $container
     */
    public function resolve(DIContainer $container): object
    {
        return $container->get($this->serviceId);
    }
}

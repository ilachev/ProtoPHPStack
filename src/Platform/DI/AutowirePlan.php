<?php

declare(strict_types=1);

namespace App\Platform\DI;

final readonly class AutowirePlan
{
    /**
     * @param class-string $className
     * @param list<AutowireArgument> $arguments
     */
    public function __construct(
        private string $className,
        private array $arguments,
    ) {}

    /**
     * @param DIContainer<object> $container
     */
    public function instantiate(DIContainer $container): object
    {
        $arguments = [];
        foreach ($this->arguments as $argument) {
            $arguments[] = $argument->resolve($container);
        }

        $className = $this->className;

        return new $className(...$arguments);
    }
}

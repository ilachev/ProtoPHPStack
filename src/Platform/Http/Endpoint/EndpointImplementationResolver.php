<?php

declare(strict_types=1);

namespace App\Platform\Http\Endpoint;

final readonly class EndpointImplementationResolver
{
    private GeneratedEndpointBindingProvider $bindingProvider;

    public function __construct(?GeneratedEndpointBindingProvider $bindingProvider = null)
    {
        $this->bindingProvider = $bindingProvider ?? new GeneratedEndpointBindingProvider(
            \dirname(__DIR__, 4) . '/gen/Generated/EndpointBindings',
        );
    }

    /**
     * @template T of object
     * @param class-string<T> $interface
     * @return class-string<T>|null
     */
    public function resolve(string $interface): ?string
    {
        $implementation = $this->resolveExpectedClass($interface);
        if ($implementation === null || !class_exists($implementation)) {
            return null;
        }

        /** @var class-string<T> $implementation */
        return $implementation;
    }

    /**
     * @template T of object
     * @param class-string<T> $interface
     * @return class-string<T>|null
     */
    public function resolveExpectedClass(string $interface): ?string
    {
        $implementation = $this->bindingProvider->getBindings()[$interface] ?? null;
        if ($implementation === null) {
            return null;
        }

        /** @var class-string<T> $implementation */
        return $implementation;
    }
}

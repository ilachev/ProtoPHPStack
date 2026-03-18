<?php

declare(strict_types=1);

namespace App\Platform\Http\Endpoint;

use App\Platform\Http\GeneratedOperationManifestProvider;

final readonly class EndpointImplementationResolver
{
    private GeneratedEndpointImplementationMapProvider $implementationMapProvider;

    public function __construct(?GeneratedEndpointImplementationMapProvider $implementationMapProvider = null)
    {
        $this->implementationMapProvider = $implementationMapProvider ?? new GeneratedEndpointImplementationMapProvider(
            new GeneratedOperationManifestProvider(\dirname(__DIR__, 4) . '/gen/Generated/OperationManifest'),
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
        $implementation = $this->implementationMapProvider->getImplementations()[$interface] ?? null;
        if ($implementation === null) {
            return null;
        }

        /** @var class-string<T> $implementation */
        return $implementation;
    }
}

<?php

declare(strict_types=1);

namespace App\Platform\Http\Endpoint;

final readonly class EndpointImplementationResolver
{
    private const GENERATED_ENDPOINT_NAMESPACE_PREFIX = 'App\Generated\Transport\\';
    private const HANDWRITTEN_ENDPOINT_NAMESPACE_PREFIX = 'App\Platform\Http\Endpoint\\';

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
        if (!str_starts_with($interface, self::GENERATED_ENDPOINT_NAMESPACE_PREFIX)) {
            return null;
        }

        $relativeClass = substr($interface, \strlen(self::GENERATED_ENDPOINT_NAMESPACE_PREFIX));
        if ($relativeClass === '') {
            return null;
        }

        /** @var class-string<T> */
        return self::HANDWRITTEN_ENDPOINT_NAMESPACE_PREFIX . $relativeClass;
    }
}

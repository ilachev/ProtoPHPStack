<?php

declare(strict_types=1);

namespace App\Platform\Hydration;

/**
 * Adapter for mapping JSON storage fields to hydrated PHP objects.
 */
interface JsonFieldAdapter
{
    /**
     * @param string $jsonValue JSON string read from storage
     * @param class-string<object> $targetClass
     * @param (callable(array<string, mixed>): array<string, mixed>)|null $fieldTransformer
     */
    public function deserialize(
        string $jsonValue,
        string $targetClass,
        ?callable $fieldTransformer = null,
    ): object;

    /**
     * @param (callable(array<string, mixed>): array<string, mixed>)|null $fieldTransformer
     */
    public function serialize(object $object, ?callable $fieldTransformer = null): string;

    /**
     * @param string $jsonValue JSON string read from storage
     * @param class-string<object> $targetClass
     * @param (callable(array<string, mixed>): array<string, mixed>)|null $fieldTransformer
     */
    public function tryDeserialize(
        string $jsonValue,
        string $targetClass,
        object $defaultValue,
        ?callable $fieldTransformer = null,
    ): object;

    /**
     * @param string $defaultJson JSON value returned on failure
     * @param (callable(array<string, mixed>): array<string, mixed>)|null $fieldTransformer
     */
    public function trySerialize(
        object $object,
        string $defaultJson = '{}',
        ?callable $fieldTransformer = null,
    ): string;
}

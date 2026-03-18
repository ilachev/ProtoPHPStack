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
     */
    public function deserialize(
        string $jsonValue,
        string $targetClass,
    ): object;

    public function serialize(object $object): string;

    /**
     * @param string $jsonValue JSON string read from storage
     * @param class-string<object> $targetClass
     */
    public function tryDeserialize(
        string $jsonValue,
        string $targetClass,
        object $defaultValue,
    ): object;

    /**
     * @param string $defaultJson JSON value returned on failure
     */
    public function trySerialize(
        object $object,
        string $defaultJson = '{}',
    ): string;
}

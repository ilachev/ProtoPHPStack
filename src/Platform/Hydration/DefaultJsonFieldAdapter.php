<?php

declare(strict_types=1);

namespace App\Platform\Hydration;

/**
 * Bridges JSON database fields and hydrated PHP objects.
 */
final readonly class DefaultJsonFieldAdapter implements JsonFieldAdapter
{
    public function __construct(
        private Hydrator $hydrator,
    ) {}

    /**
     * @param string $jsonValue JSON string read from storage
     * @param class-string<object> $targetClass
     */
    public function deserialize(
        string $jsonValue,
        string $targetClass,
    ): object {
        $data = json_decode($jsonValue, true);

        if (!\is_array($data)) {
            throw new HydratorException("Invalid JSON data for {$targetClass}");
        }

        /** @var array<string, mixed> $typedData */
        $typedData = $data;

        if (!class_exists($targetClass)) {
            throw new HydratorException("Target class {$targetClass} does not exist");
        }

        $result = $this->hydrator->hydrate($targetClass, $typedData);

        return $result;
    }

    public function serialize(object $object): string
    {
        $data = $this->hydrator->extract($object);

        /** @var array<string, mixed> $typedData */
        $typedData = $data;

        $json = json_encode($typedData);

        if ($json === false) {
            throw new HydratorException(
                'Failed to encode object to JSON: ' . json_last_error_msg(),
            );
        }

        return $json;
    }

    /**
     * @param string $jsonValue JSON string read from storage
     * @param class-string<object> $targetClass
     */
    public function tryDeserialize(
        string $jsonValue,
        string $targetClass,
        object $defaultValue,
    ): object {
        try {
            return $this->deserialize($jsonValue, $targetClass);
        } catch (HydratorException $e) {
            return $defaultValue;
        }
    }

    /**
     * @param string $defaultJson JSON value returned on failure
     */
    public function trySerialize(
        object $object,
        string $defaultJson = '{}',
    ): string {
        try {
            return $this->serialize($object);
        } catch (HydratorException $e) {
            return $defaultJson;
        }
    }
}

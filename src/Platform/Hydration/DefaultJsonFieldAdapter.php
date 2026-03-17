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
     * @param (callable(array<string, mixed>): array<string, mixed>)|null $fieldTransformer
     */
    public function deserialize(
        string $jsonValue,
        string $targetClass,
        ?callable $fieldTransformer = null,
    ): object {
        $data = json_decode($jsonValue, true);

        if (!\is_array($data)) {
            throw new HydratorException("Invalid JSON data for {$targetClass}");
        }

        /** @var array<string, mixed> $typedData */
        $typedData = $data;

        if ($fieldTransformer !== null) {
            $typedData = $fieldTransformer($typedData);
        }

        if (!class_exists($targetClass)) {
            throw new HydratorException("Target class {$targetClass} does not exist");
        }

        $result = $this->hydrator->hydrate($targetClass, $typedData);

        return $result;
    }

    /**
     * @param (callable(array<string, mixed>): array<string, mixed>)|null $fieldTransformer
     */
    public function serialize(object $object, ?callable $fieldTransformer = null): string
    {
        $data = $this->hydrator->extract($object);

        /** @var array<string, mixed> $typedData */
        $typedData = $data;

        if ($fieldTransformer !== null) {
            $typedData = $fieldTransformer($typedData);
        }

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
     * @param (callable(array<string, mixed>): array<string, mixed>)|null $fieldTransformer
     */
    public function tryDeserialize(
        string $jsonValue,
        string $targetClass,
        object $defaultValue,
        ?callable $fieldTransformer = null,
    ): object {
        try {
            return $this->deserialize($jsonValue, $targetClass, $fieldTransformer);
        } catch (HydratorException $e) {
            return $defaultValue;
        }
    }

    /**
     * @param string $defaultJson JSON value returned on failure
     * @param (callable(array<string, mixed>): array<string, mixed>)|null $fieldTransformer
     */
    public function trySerialize(
        object $object,
        string $defaultJson = '{}',
        ?callable $fieldTransformer = null,
    ): string {
        try {
            return $this->serialize($object, $fieldTransformer);
        } catch (HydratorException $e) {
            return $defaultJson;
        }
    }
}

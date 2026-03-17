<?php

declare(strict_types=1);

namespace App\Platform\Cache;

use Spiral\RoadRunner\KeyValue\Serializer\SerializerInterface;
use Spiral\RoadRunner\KeyValue\StorageInterface;

/**
 * Null-object storage used when the primary backend is unavailable.
 */
final class FallbackStorage implements StorageInterface
{
    public function get(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        return true;
    }

    public function delete(string $key): bool
    {
        return true;
    }

    public function clear(): bool
    {
        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $default;
        }

        return $result;
    }

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        return true;
    }

    public function has(string $key): bool
    {
        return false;
    }

    public function getTtl(string $key): ?\DateTimeInterface
    {
        return null;
    }

    /**
     * @param iterable<string> $keys
     * @return array<string, \DateTimeInterface|null>
     */
    public function getMultipleTtl(iterable $keys = []): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = null;
        }

        return $result;
    }

    private ?SerializerInterface $serializer = null;

    public function withSerializer(SerializerInterface $serializer): self
    {
        $this->serializer = $serializer;

        return $this;
    }

    public function getSerializer(): SerializerInterface
    {
        // Provide a minimal serializer when none was configured.
        return $this->serializer ?? new class implements SerializerInterface {
            public function serialize(mixed $value): string
            {
                if (\is_string($value)) {
                    return $value;
                }

                $encoded = json_encode($value);

                return $encoded !== false ? $encoded : '';
            }

            public function unserialize(string $value): mixed
            {
                return $value;
            }
        };
    }

    public function getName(): string
    {
        return 'fallback';
    }
}

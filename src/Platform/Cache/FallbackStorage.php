<?php

declare(strict_types=1);

namespace App\Platform\Cache;

use Spiral\RoadRunner\KeyValue\Serializer\SerializerInterface;
use Spiral\RoadRunner\KeyValue\StorageInterface;

/**
 * Bounded in-memory storage used when the primary backend is unavailable.
 */
final class FallbackStorage implements StorageInterface
{
    /**
     * @var array<string, array{
     *     value: mixed,
     *     expiresAt: int|null,
     *     lastAccess: int,
     * }>
     */
    private array $entries = [];

    private ?SerializerInterface $serializer = null;

    public function __construct(
        private readonly int $maxEntries = 1000,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $this->purgeExpired();

        if (!\array_key_exists($key, $this->entries)) {
            return $default;
        }

        $this->touch($key);

        return $this->entries[$key]['value'];
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $this->purgeExpired();

        $this->entries[$key] = [
            'value' => $value,
            'expiresAt' => $this->normalizeTtl($ttl),
            'lastAccess' => $this->now(),
        ];

        $this->evictLeastRecentlyUsed();

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->entries[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->entries = [];

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get((string) $key, $default);
        }

        return $result;
    }

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete((string) $key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        $this->purgeExpired();

        if (!\array_key_exists($key, $this->entries)) {
            return false;
        }

        $this->touch($key);

        return true;
    }

    public function getTtl(string $key): ?\DateTimeInterface
    {
        $this->purgeExpired();

        if (!\array_key_exists($key, $this->entries)) {
            return null;
        }

        $expiresAt = $this->entries[$key]['expiresAt'];
        if ($expiresAt === null) {
            return null;
        }

        return (new \DateTimeImmutable())->setTimestamp($expiresAt);
    }

    /**
     * @param iterable<non-empty-string> $keys
     * @return array<non-empty-string, \DateTimeInterface|null>
     */
    public function getMultipleTtl(iterable $keys = []): iterable
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->getTtl($key);
        }

        return $result;
    }

    public function withSerializer(SerializerInterface $serializer): self
    {
        $this->serializer = $serializer;

        return $this;
    }

    public function getSerializer(): SerializerInterface
    {
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

    private function purgeExpired(): void
    {
        $now = $this->now();

        foreach ($this->entries as $key => $entry) {
            if ($entry['expiresAt'] !== null && $entry['expiresAt'] <= $now) {
                unset($this->entries[$key]);
            }
        }
    }

    private function evictLeastRecentlyUsed(): void
    {
        while (\count($this->entries) > $this->maxEntries) {
            $oldestKey = null;
            $oldestAccess = null;

            foreach ($this->entries as $key => $entry) {
                if ($oldestAccess === null || $entry['lastAccess'] < $oldestAccess) {
                    $oldestKey = $key;
                    $oldestAccess = $entry['lastAccess'];
                }
            }

            if ($oldestKey === null) {
                return;
            }

            unset($this->entries[$oldestKey]);
        }
    }

    private function touch(string $key): void
    {
        if (!\array_key_exists($key, $this->entries)) {
            return;
        }

        $this->entries[$key]['lastAccess'] = $this->now();
    }

    private function normalizeTtl(null|int|\DateInterval $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }

        $now = new \DateTimeImmutable();

        if ($ttl instanceof \DateInterval) {
            return $now->add($ttl)->getTimestamp();
        }

        return $now->modify("+{$ttl} seconds")->getTimestamp();
    }

    private function now(): int
    {
        return time();
    }
}

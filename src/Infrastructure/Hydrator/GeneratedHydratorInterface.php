<?php

declare(strict_types=1);

namespace App\Infrastructure\Hydrator;

/**
 * Interface for generated hydrator classes.
 *
 * These hydrators are generated at runtime to provide a high-performance,
 * reflection-free way to hydrate and extract data from objects.
 */
interface GeneratedHydratorInterface
{
    /**
     * Hydrates an object with data from an array.
     * This implementation is generated and contains no reflection.
     *
     * @param array<string, mixed> $data
     */
    public function hydrate(array $data): object;

    /**
     * Extracts data from an object into an array.
     * This implementation is generated and contains no reflection.
     *
     * @return array<string, mixed>
     */
    public function extract(object $object): array;
}

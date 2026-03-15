<?php

declare(strict_types=1);

namespace App\Infrastructure\Hydrator;

/**
 * Interface for hydrating objects from arrays and extracting data from objects.
 */
interface HydratorInterface
{
    /**
     * Hydrates an object of the given class with data from an array.
     *
     * @template T of object
     * @param class-string<T> $className the name of the class to hydrate
     * @param array<string, mixed> $data the data to hydrate the object with
     * @return T the hydrated object
     */
    public function hydrate(string $className, array $data): object;

    /**
     * Extracts data from an object into an array.
     *
     * @param object $object the object to extract data from
     * @return array<string, mixed> the extracted data
     */
    public function extract(object $object): array;
}

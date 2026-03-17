<?php

declare(strict_types=1);

namespace App\Platform\Hydration\Codegen;

/**
 * Interface for generated hydrator classes.
 *
 * These hydrators are generated at runtime to provide a high-performance,
 * reflection-free way to hydrate and extract data from objects.
 */
interface GeneratedHydrator
{
    /**
     * @param array<string, mixed> $data
     */
    public function hydrate(array $data): object;

    /**
     * @return array<string, mixed>
     */
    public function extract(object $object): array;
}

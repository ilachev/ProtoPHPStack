<?php

declare(strict_types=1);

namespace App\Platform\Hydration;

interface ProtobufHydration
{
    /**
     * @template T of object
     * @param class-string<T> $className
     * @param array<string, mixed> $data
     * @return T
     */
    public function hydrate(string $className, array $data): object;
}

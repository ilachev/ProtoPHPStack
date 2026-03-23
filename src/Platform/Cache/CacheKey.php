<?php

declare(strict_types=1);

namespace App\Platform\Cache;

final readonly class CacheKey
{
    public function __construct(
        public CacheScope $scope,
        public string $identifier,
    ) {}

    public function toString(): string
    {
        return $this->scope->name . ':' . $this->identifier;
    }
}

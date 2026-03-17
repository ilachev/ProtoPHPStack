<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Hydration\Fixtures;

final readonly class TestEntity
{
    public function __construct(
        public int $id,
        public string $name,
        public bool $initialized = false,
    ) {}
}

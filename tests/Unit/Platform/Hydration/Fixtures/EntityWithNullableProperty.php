<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Hydration\Fixtures;

final readonly class EntityWithNullableProperty
{
    public function __construct(
        public ?string $nullableField,
    ) {}
}

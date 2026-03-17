<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\DI\Fixtures;

final readonly class ServiceWithNestedDependency
{
    public function __construct(
        public ParentDependency $parent,
    ) {}
}

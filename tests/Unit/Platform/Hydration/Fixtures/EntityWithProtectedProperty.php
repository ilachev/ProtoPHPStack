<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Hydration\Fixtures;

abstract readonly class EntityWithProtectedPropertyBase
{
    protected string $protectedField;

    public function __construct(string $protectedField = '')
    {
        $this->protectedField = $protectedField;
    }

    final public function getProtectedField(): string
    {
        return $this->protectedField;
    }
}

final readonly class EntityWithProtectedProperty extends EntityWithProtectedPropertyBase {}

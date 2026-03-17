<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\DI\Fixtures;

final readonly class Circular1
{
    public function __construct(public Circular2 $c2) {}
}

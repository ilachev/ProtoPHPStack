<?php

declare(strict_types=1);

namespace App\Platform\Runtime;

interface RequestIdGenerator
{
    public function generate(): string;
}

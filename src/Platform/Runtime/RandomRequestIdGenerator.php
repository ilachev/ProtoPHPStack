<?php

declare(strict_types=1);

namespace App\Platform\Runtime;

final class RandomRequestIdGenerator implements RequestIdGenerator
{
    public function generate(): string
    {
        return bin2hex(random_bytes(8));
    }
}

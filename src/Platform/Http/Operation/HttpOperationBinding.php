<?php

declare(strict_types=1);

namespace App\Platform\Http\Operation;

final readonly class HttpOperationBinding
{
    public function __construct(
        public string $method,
        public string $path,
    ) {}
}

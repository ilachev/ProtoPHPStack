<?php

declare(strict_types=1);

namespace App\Platform\Http;

final readonly class HttpRuntimeConfig
{
    public function __construct(
        public float $requestTimeoutSeconds = 15.0,
    ) {}
}

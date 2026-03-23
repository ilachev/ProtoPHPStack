<?php

declare(strict_types=1);

namespace App\Platform\Http\Client;

use App\Platform\Runtime\Deadline;

final readonly class HttpRequestOptions
{
    public function __construct(
        public float $connectTimeoutSeconds = 3.0,
        public float $timeoutSeconds = 10.0,
        public bool $followRedirects = true,
        public int $maxRedirects = 3,
        public bool $idempotent = true,
        public ?string $userAgent = null,
        public ?Deadline $deadline = null,
        public RetryPolicy $retryPolicy = new RetryPolicy(),
    ) {}
}

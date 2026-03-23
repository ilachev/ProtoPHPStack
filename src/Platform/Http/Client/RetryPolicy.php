<?php

declare(strict_types=1);

namespace App\Platform\Http\Client;

final readonly class RetryPolicy
{
    /**
     * @param list<int> $retryableStatusCodes
     */
    public function __construct(
        public int $maxAttempts = 2,
        public int $baseDelayMilliseconds = 100,
        public int $maxDelayMilliseconds = 1000,
        public array $retryableStatusCodes = [429, 500, 502, 503, 504],
    ) {}
}

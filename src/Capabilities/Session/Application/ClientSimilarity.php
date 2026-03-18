<?php

declare(strict_types=1);

namespace App\Capabilities\Session\Application;

final readonly class ClientSimilarity
{
    public function __construct(
        public ClientIdentity $identity,
        public float $score,
    ) {}
}

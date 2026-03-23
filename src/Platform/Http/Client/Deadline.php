<?php

declare(strict_types=1);

namespace App\Platform\Http\Client;

use App\Platform\Http\Client\Exception\HttpTimeoutException;

final readonly class Deadline
{
    private function __construct(
        private int $startedAtNanoseconds,
        private int $timeoutMilliseconds,
    ) {}

    public static function fromSeconds(float $timeoutSeconds): self
    {
        return new self(
            startedAtNanoseconds: hrtime(true),
            timeoutMilliseconds: max(1, (int) ceil($timeoutSeconds * 1000)),
        );
    }

    public function remainingMilliseconds(): int
    {
        $elapsedMilliseconds = (int) floor((hrtime(true) - $this->startedAtNanoseconds) / 1_000_000);

        return max(0, $this->timeoutMilliseconds - $elapsedMilliseconds);
    }

    public function assertRemaining(HttpRequest $request, int $attempt): void
    {
        if ($this->remainingMilliseconds() > 0) {
            return;
        }

        throw HttpTimeoutException::forBudgetExhaustion($request, $attempt);
    }
}

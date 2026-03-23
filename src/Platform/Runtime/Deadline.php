<?php

declare(strict_types=1);

namespace App\Platform\Runtime;

final readonly class Deadline
{
    private function __construct(
        private int $startedAtNanoseconds,
        private int $budgetMilliseconds,
    ) {}

    public static function fromSeconds(float $timeoutSeconds): self
    {
        return self::fromMilliseconds((int) ceil($timeoutSeconds * 1000));
    }

    public static function fromMilliseconds(int $timeoutMilliseconds): self
    {
        return new self(
            startedAtNanoseconds: hrtime(true),
            budgetMilliseconds: max(0, $timeoutMilliseconds),
        );
    }

    public function remainingMilliseconds(): int
    {
        $elapsedMilliseconds = (int) floor((hrtime(true) - $this->startedAtNanoseconds) / 1_000_000);

        return max(0, $this->budgetMilliseconds - $elapsedMilliseconds);
    }

    public function isExhausted(): bool
    {
        return $this->remainingMilliseconds() <= 0;
    }

    public function sliceSeconds(float $timeoutSeconds): self
    {
        return $this->sliceMilliseconds((int) ceil($timeoutSeconds * 1000));
    }

    public function sliceMilliseconds(int $timeoutMilliseconds): self
    {
        return self::fromMilliseconds(min($this->remainingMilliseconds(), max(0, $timeoutMilliseconds)));
    }
}

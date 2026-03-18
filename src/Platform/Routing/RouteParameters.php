<?php

declare(strict_types=1);

namespace App\Platform\Routing;

final readonly class RouteParameters
{
    /**
     * @param array<string, string> $values
     */
    public function __construct(
        private array $values = [],
    ) {}

    /**
     * @param array<string, string> $values
     */
    public static function fromArray(array $values): self
    {
        return new self($values);
    }

    public static function empty(): self
    {
        return new self();
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    public function merge(self $other): self
    {
        return new self(array_merge($this->values, $other->values));
    }
}

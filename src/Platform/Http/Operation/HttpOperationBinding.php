<?php

declare(strict_types=1);

namespace App\Platform\Http\Operation;

final readonly class HttpOperationBinding
{
    public function __construct(
        public string $method,
        public string $path,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $method = $data['method'] ?? null;
        $path = $data['path'] ?? null;

        if (!\is_string($method) || !\is_string($path)) {
            return null;
        }

        return new self($method, $path);
    }
}

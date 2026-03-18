<?php

declare(strict_types=1);

namespace ProtoPhpGen\Descriptor;

final readonly class HttpBindingDescriptor
{
    public function __construct(
        private string $method,
        private string $path,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            method: isset($data['method']) && \is_string($data['method']) ? strtoupper($data['method']) : '',
            path: isset($data['path']) && \is_string($data['path']) ? $data['path'] : '',
        );
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}

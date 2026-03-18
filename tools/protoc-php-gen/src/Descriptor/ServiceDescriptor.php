<?php

declare(strict_types=1);

namespace ProtoPhpGen\Descriptor;

final readonly class ServiceDescriptor
{
    /**
     * @param list<MethodDescriptor> $methods
     */
    public function __construct(
        private string $name,
        private array $methods,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $methods = [];
        $rawMethods = $data['method'] ?? [];

        if (\is_array($rawMethods)) {
            foreach ($rawMethods as $method) {
                if (!\is_array($method)) {
                    continue;
                }

                $methods[] = MethodDescriptor::fromArray($method);
            }
        }

        return new self(
            name: isset($data['name']) && \is_string($data['name']) ? $data['name'] : '',
            methods: $methods,
        );
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return list<MethodDescriptor>
     */
    public function getMethods(): array
    {
        return $this->methods;
    }
}

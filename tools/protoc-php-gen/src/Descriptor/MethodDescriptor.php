<?php

declare(strict_types=1);

namespace ProtoPhpGen\Descriptor;

final readonly class MethodDescriptor
{
    /**
     * @param list<HttpBindingDescriptor> $httpBindings
     */
    public function __construct(
        private string $name,
        private string $inputType,
        private string $outputType,
        private array $httpBindings = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $httpBindings = [];
        $rawBindings = $data['http_bindings'] ?? [];

        if (\is_array($rawBindings)) {
            foreach ($rawBindings as $binding) {
                if (!\is_array($binding)) {
                    continue;
                }

                $httpBindings[] = HttpBindingDescriptor::fromArray($binding);
            }
        }

        return new self(
            name: isset($data['name']) && \is_string($data['name']) ? $data['name'] : '',
            inputType: isset($data['input_type']) && \is_string($data['input_type']) ? $data['input_type'] : '',
            outputType: isset($data['output_type']) && \is_string($data['output_type']) ? $data['output_type'] : '',
            httpBindings: $httpBindings,
        );
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getInputType(): string
    {
        return $this->inputType;
    }

    public function getOutputType(): string
    {
        return $this->outputType;
    }

    /**
     * @return list<HttpBindingDescriptor>
     */
    public function getHttpBindings(): array
    {
        return $this->httpBindings;
    }
}

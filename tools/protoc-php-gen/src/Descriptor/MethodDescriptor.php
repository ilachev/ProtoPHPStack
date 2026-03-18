<?php

declare(strict_types=1);

namespace ProtoPhpGen\Descriptor;

final readonly class MethodDescriptor
{
    public function __construct(
        private string $name,
        private string $inputType,
        private string $outputType,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: isset($data['name']) && \is_string($data['name']) ? $data['name'] : '',
            inputType: isset($data['input_type']) && \is_string($data['input_type']) ? $data['input_type'] : '',
            outputType: isset($data['output_type']) && \is_string($data['output_type']) ? $data['output_type'] : '',
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
}

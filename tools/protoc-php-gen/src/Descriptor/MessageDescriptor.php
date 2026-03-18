<?php

declare(strict_types=1);

namespace ProtoPhpGen\Descriptor;

final readonly class MessageDescriptor
{
    /**
     * @param list<MessageDescriptor> $nestedMessages
     */
    public function __construct(
        private string $name,
        private array $nestedMessages = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $nestedMessages = [];
        $nestedTypes = $data['nested_type'] ?? [];

        if (\is_array($nestedTypes)) {
            foreach ($nestedTypes as $nestedType) {
                if (!\is_array($nestedType)) {
                    continue;
                }

                $nestedMessages[] = self::fromArray($nestedType);
            }
        }

        return new self(
            name: isset($data['name']) && \is_string($data['name']) ? $data['name'] : '',
            nestedMessages: $nestedMessages,
        );
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return list<MessageDescriptor>
     */
    public function getNestedMessages(): array
    {
        return $this->nestedMessages;
    }
}

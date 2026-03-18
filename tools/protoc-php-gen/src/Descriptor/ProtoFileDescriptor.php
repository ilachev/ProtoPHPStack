<?php

declare(strict_types=1);

namespace ProtoPhpGen\Descriptor;

final readonly class ProtoFileDescriptor
{
    /**
     * @param list<string> $dependencies
     * @param list<MessageDescriptor> $messages
     * @param list<ServiceDescriptor> $services
     */
    public function __construct(
        private string $name,
        private string $package,
        private array $dependencies = [],
        private array $messages = [],
        private array $services = [],
        private ?FileOptionsDescriptor $options = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $dependencies = [];
        $rawDependencies = $data['dependency'] ?? [];
        if (\is_array($rawDependencies)) {
            foreach ($rawDependencies as $dependency) {
                if (\is_string($dependency) && $dependency !== '') {
                    $dependencies[] = $dependency;
                }
            }
        }

        $messages = [];
        $rawMessages = $data['message_type'] ?? [];
        if (\is_array($rawMessages)) {
            foreach ($rawMessages as $message) {
                if (!\is_array($message)) {
                    continue;
                }

                $messages[] = MessageDescriptor::fromArray($message);
            }
        }

        $services = [];
        $rawServices = $data['service'] ?? [];
        if (\is_array($rawServices)) {
            foreach ($rawServices as $service) {
                if (!\is_array($service)) {
                    continue;
                }

                $services[] = ServiceDescriptor::fromArray($service);
            }
        }

        $options = null;
        $rawOptions = $data['options'] ?? null;
        if (\is_array($rawOptions)) {
            $options = FileOptionsDescriptor::fromArray($rawOptions);
        }

        return new self(
            name: isset($data['name']) && \is_string($data['name']) ? $data['name'] : '',
            package: isset($data['package']) && \is_string($data['package']) ? $data['package'] : '',
            dependencies: $dependencies,
            messages: $messages,
            services: $services,
            options: $options,
        );
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPackage(): string
    {
        return $this->package;
    }

    /**
     * @return list<string>
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    /**
     * @return list<MessageDescriptor>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * @return list<ServiceDescriptor>
     */
    public function getServices(): array
    {
        return $this->services;
    }

    public function getOptions(): ?FileOptionsDescriptor
    {
        return $this->options;
    }
}

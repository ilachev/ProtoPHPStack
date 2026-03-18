<?php

declare(strict_types=1);

namespace ProtoPhpGen\Profile;

final readonly class TransportProfileRegistry
{
    /**
     * @param list<TransportProfile> $profiles
     */
    public function __construct(
        private array $profiles = [
            new BaseApiTemplateTransportProfile(),
        ],
    ) {}

    public function get(string $name): TransportProfile
    {
        foreach ($this->profiles as $profile) {
            if ($profile->getName() === $name) {
                return $profile;
            }
        }

        throw new \InvalidArgumentException("Unknown transport profile: {$name}");
    }
}

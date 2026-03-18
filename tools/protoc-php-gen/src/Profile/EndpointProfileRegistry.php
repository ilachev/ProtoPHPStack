<?php

declare(strict_types=1);

namespace ProtoPhpGen\Profile;

final readonly class EndpointProfileRegistry
{
    /**
     * @param list<EndpointProfile> $profiles
     */
    public function __construct(
        private array $profiles = [
            new BaseApiTemplateEndpointProfile(),
        ],
    ) {}

    public function get(string $name): EndpointProfile
    {
        foreach ($this->profiles as $profile) {
            if ($profile->getName() === $name) {
                return $profile;
            }
        }

        throw new \InvalidArgumentException("Unknown endpoint profile: {$name}");
    }
}

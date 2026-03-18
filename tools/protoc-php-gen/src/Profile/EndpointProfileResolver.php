<?php

declare(strict_types=1);

namespace ProtoPhpGen\Profile;

final readonly class EndpointProfileResolver
{
    public function resolve(string $profileClass): EndpointProfile
    {
        if ($profileClass === '') {
            throw new \InvalidArgumentException('Missing endpoint profile class.');
        }

        if (!class_exists($profileClass)) {
            throw new \InvalidArgumentException("Unknown endpoint profile class: {$profileClass}");
        }

        if (!is_a($profileClass, EndpointProfile::class, true)) {
            throw new \InvalidArgumentException(
                'Endpoint profile class must implement ' . EndpointProfile::class . ": {$profileClass}",
            );
        }

        /** @var EndpointProfile $profile */
        $profile = new $profileClass();

        return $profile;
    }
}

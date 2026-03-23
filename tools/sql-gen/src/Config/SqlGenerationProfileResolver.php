<?php

declare(strict_types=1);

namespace SqlGen\Config;

final readonly class SqlGenerationProfileResolver
{
    public function resolve(string $profileClass): SqlGenerationProfile
    {
        if ($profileClass === '') {
            throw new \InvalidArgumentException('Missing sql generation profile class.');
        }

        if (!class_exists($profileClass)) {
            throw new \InvalidArgumentException("Unknown sql generation profile class: {$profileClass}");
        }

        if (!is_a($profileClass, SqlGenerationProfile::class, true)) {
            throw new \InvalidArgumentException(
                'SQL generation profile class must implement ' . SqlGenerationProfile::class . ": {$profileClass}",
            );
        }

        /** @var SqlGenerationProfile $profile */
        $profile = new $profileClass();

        return $profile;
    }
}

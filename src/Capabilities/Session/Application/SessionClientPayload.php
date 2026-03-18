<?php

declare(strict_types=1);

namespace App\Capabilities\Session\Application;

use App\Capabilities\Session\Domain\Session;

final readonly class SessionClientPayload
{
    /**
     * @param array<string, string> $attributes
     */
    public function __construct(
        public string $ipAddress,
        public ?string $userAgent,
        public array $attributes = [],
    ) {}

    public static function fromSession(Session $session): self
    {
        $payload = json_decode($session->payload, true);

        if (!\is_array($payload)) {
            return new self(
                ipAddress: 'unknown',
                userAgent: null,
            );
        }

        $ipAddress = isset($payload['ip']) && \is_string($payload['ip'])
            ? $payload['ip']
            : 'unknown';

        $userAgent = isset($payload['userAgent']) && \is_string($payload['userAgent'])
            ? $payload['userAgent']
            : null;

        /** @var array<string, string> $attributes */
        $attributes = [];
        foreach (self::attributeFields() as $field) {
            if (isset($payload[$field]) && \is_string($payload[$field])) {
                $attributes[$field] = $payload[$field];
            }
        }

        return new self(
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            attributes: $attributes,
        );
    }

    /**
     * @return list<string>
     */
    private static function attributeFields(): array
    {
        return [
            'acceptLanguage',
            'acceptEncoding',
            'xForwardedFor',
            'referer',
            'origin',
            'secChUa',
            'secChUaPlatform',
            'secChUaMobile',
            'dnt',
            'secFetchDest',
            'secFetchMode',
            'secFetchSite',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Capabilities\Session\Application;

use App\Capabilities\Session\Domain\Session;

final readonly class ClientIdentity
{
    /**
     * @param array<string, string> $attributes
     */
    public function __construct(
        public string $id,
        public string $ipAddress,
        public ?string $userAgent = null,
        public array $attributes = [],
    ) {}

    public static function fromSession(Session $session): self
    {
        $payload = json_decode($session->payload, true);

        if (!\is_array($payload)) {
            return new self(
                id: $session->id,
                ipAddress: 'unknown',
            );
        }

        $ipAddress = 'unknown';
        if (isset($payload['ip']) && \is_string($payload['ip'])) {
            $ipAddress = $payload['ip'];
        }

        $userAgent = null;
        if (isset($payload['userAgent']) && \is_string($payload['userAgent'])) {
            $userAgent = $payload['userAgent'];
        }

        /** @var array<string, string> $attributes */
        $attributes = [];

        $attributeFields = [
            'acceptLanguage', 'acceptEncoding', 'xForwardedFor', 'referer',
            'origin', 'secChUa', 'secChUaPlatform', 'secChUaMobile',
            'dnt', 'secFetchDest', 'secFetchMode', 'secFetchSite',
        ];

        foreach ($attributeFields as $field) {
            if (isset($payload[$field]) && \is_string($payload[$field])) {
                $attributes[$field] = $payload[$field];
            }
        }

        return new self(
            id: $session->id,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            attributes: $attributes,
        );
    }

    public function matches(self $other, bool $strictIpMatch = false): bool
    {
        if ($this->id === $other->id) {
            return true;
        }

        $ipMatches = $strictIpMatch
            ? $this->ipAddress === $other->ipAddress
            : $this->ipAddress !== 'unknown' && $this->ipAddress === $other->ipAddress;

        $uaMatches = $this->userAgent !== null && $this->userAgent === $other->userAgent;

        return ($ipMatches && $uaMatches)
            || ($ipMatches && \count(array_intersect_assoc($this->attributes, $other->attributes)) > 0);
    }
}

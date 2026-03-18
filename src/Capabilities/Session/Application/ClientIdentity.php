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
        $payload = SessionClientPayload::fromSession($session);

        return new self(
            id: $session->id,
            ipAddress: $payload->ipAddress,
            userAgent: $payload->userAgent,
            attributes: $payload->attributes,
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

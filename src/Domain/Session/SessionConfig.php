<?php

declare(strict_types=1);

namespace App\Domain\Session;

final readonly class SessionConfig
{
    public function __construct(
        public string $cookieName,
        public int $cookieTtl,
        public int $sessionTtl,
        public bool $useFingerprint,
        public bool $browserNewSession,
    ) {}

    /**
     * @param array{
     *     cookie_name?: string,
     *     cookie_ttl?: int,
     *     session_ttl?: int,
     *     use_fingerprint?: bool,
     *     browser_new_session?: bool
     * } $config
     */
    public static function fromArray(array $config): self
    {
        $sessionTtl = $config['session_ttl'] ?? 3600;

        return new self(
            cookieName: $config['cookie_name'] ?? 'session',
            cookieTtl: (int) ($config['cookie_ttl'] ?? 86400),
            sessionTtl: $sessionTtl === -1 ? PHP_INT_MAX : (int) $sessionTtl,
            useFingerprint: (bool) ($config['use_fingerprint'] ?? true),
            browserNewSession: (bool) ($config['browser_new_session'] ?? true),
        );
    }
}

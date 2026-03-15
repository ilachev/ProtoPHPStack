<?php

declare(strict_types=1);

namespace App\Capabilities\Session\Application;

final readonly class ClientConfig
{
    public function __construct(
        public float $similarityThreshold = 0.6,
        public int $maxSessionsPerIp = 5,
        public float $ipMatchWeight = 0.3,
        public float $userAgentMatchWeight = 0.3,
        public float $attributesMatchWeight = 0.4,
    ) {
        $sum = $this->ipMatchWeight + $this->userAgentMatchWeight + $this->attributesMatchWeight;
        if (abs($sum - 1.0) > 0.001) {
            throw new \InvalidArgumentException(
                "Sum of match weights must be 1.0, got {$sum}",
            );
        }
    }

    /**
     * @param array{
     *    similarity_threshold?: float,
     *    max_sessions_per_ip?: int,
     *    ip_match_weight?: float,
     *    user_agent_match_weight?: float,
     *    attributes_match_weight?: float,
     * } $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            similarityThreshold: $config['similarity_threshold'] ?? 0.6,
            maxSessionsPerIp: $config['max_sessions_per_ip'] ?? 5,
            ipMatchWeight: $config['ip_match_weight'] ?? 0.3,
            userAgentMatchWeight: $config['user_agent_match_weight'] ?? 0.3,
            attributesMatchWeight: $config['attributes_match_weight'] ?? 0.4,
        );
    }
}

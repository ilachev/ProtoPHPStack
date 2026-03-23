<?php

declare(strict_types=1);

return [
    // Storage name in RoadRunner KV (must match the name in .rr.yaml)
    'engine' => 'redis',

    // RPC connection address for RoadRunner
    'address' => 'tcp://127.0.0.1:6001',

    // Key prefix - helps avoid conflicts when using shared Redis
    'default_prefix' => 'app:',

    // Optional deployment namespace seed for rolling cache invalidation without global clear
    'namespace_seed' => $_SERVER['CACHE_NAMESPACE_SEED'] ?? '',

    // Default cache TTL in seconds (1 hour)
    'default_ttl' => 3600,

    // Enable data compression for large values (to save memory)
    'compression' => true,

    // Compression threshold in bytes (compress values larger than this size)
    'compression_threshold' => 1024,

    // Maximum key storage time (7 days) - Redis limitation
    'max_ttl' => 604800,

    // Maximum number of entries in the in-memory fallback cache
    'fallback_max_entries' => 1000,
];

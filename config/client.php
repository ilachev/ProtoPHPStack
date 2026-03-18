<?php

declare(strict_types=1);

use App\Capabilities\Session\Application\ClientConfig;

return new ClientConfig(
    similarityThreshold: 0.6,
    maxSessionsPerIp: 5,
    ipMatchWeight: 0.3,
    userAgentMatchWeight: 0.3,
    attributesMatchWeight: 0.4,
);

<?php

declare(strict_types=1);

namespace App\Platform\Http\Endpoint;

use App\Api\V1\HealthCheckRequest;
use App\Api\V1\HealthCheckResponse;
use App\Generated\Transport\Api\V1\HealthService\CheckEndpoint;
use Psr\Http\Message\ServerRequestInterface;

final readonly class PlatformHealthCheckEndpoint implements CheckEndpoint
{
    public function handle(HealthCheckRequest $request, ServerRequestInterface $httpRequest): HealthCheckResponse
    {
        return (new HealthCheckResponse())
            ->setStatus('ok')
            ->setTimestamp(time());
    }
}

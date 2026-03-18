<?php

declare(strict_types=1);

namespace App\Platform\Http\Endpoint\Api\V1\HealthService;

use App\Api\V1\HealthCheckRequest;
use App\Api\V1\HealthCheckResponse;
use Psr\Http\Message\ServerRequestInterface;

final readonly class CheckEndpoint implements \App\Generated\Transport\Api\V1\HealthService\CheckEndpoint
{
    public function handle(HealthCheckRequest $request, ServerRequestInterface $httpRequest): HealthCheckResponse
    {
        return (new HealthCheckResponse())
            ->setStatus('ok')
            ->setTimestamp(time());
    }
}

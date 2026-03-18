<?php

declare(strict_types=1);

namespace App\Generated\Endpoint\Api\V1\HealthService;

use App\Api\V1\HealthCheckRequest;
use App\Api\V1\HealthCheckResponse;
use Psr\Http\Message\ServerRequestInterface;

interface CheckEndpoint
{
    function handle(HealthCheckRequest $request, ServerRequestInterface $httpRequest): HealthCheckResponse;
}

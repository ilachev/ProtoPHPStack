<?php

declare(strict_types=1);

namespace App\Generated\Transport\Api\V1\HomeService;

use App\Api\V1\HomeRequest;
use App\Api\V1\HomeResponse;
use Psr\Http\Message\ServerRequestInterface;

interface HomeEndpoint
{
    function handle(HomeRequest $request, ServerRequestInterface $httpRequest): HomeResponse;
}

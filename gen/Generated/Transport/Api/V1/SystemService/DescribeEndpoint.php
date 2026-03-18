<?php

declare(strict_types=1);

namespace App\Generated\Transport\Api\V1\SystemService;

use App\Api\V1\SystemDescribeRequest;
use App\Api\V1\SystemDescribeResponse;
use Psr\Http\Message\ServerRequestInterface;

interface DescribeEndpoint
{
    function handle(SystemDescribeRequest $request, ServerRequestInterface $httpRequest): SystemDescribeResponse;
}

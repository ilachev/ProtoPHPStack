<?php

declare(strict_types=1);

namespace App\Generated\Transport\Api\V1\AuthService;

use App\Api\V1\LogoutRequest;
use App\Api\V1\LogoutResponse;
use Psr\Http\Message\ServerRequestInterface;

interface LogoutEndpoint
{
    function handle(LogoutRequest $request, ServerRequestInterface $httpRequest): LogoutResponse;
}

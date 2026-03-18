<?php

declare(strict_types=1);

namespace App\Generated\Transport\Api\V1\AuthService;

use App\Api\V1\LoginRequest;
use App\Api\V1\LoginResponse;
use Psr\Http\Message\ServerRequestInterface;

interface LoginEndpoint
{
    function handle(LoginRequest $request, ServerRequestInterface $httpRequest): LoginResponse;
}

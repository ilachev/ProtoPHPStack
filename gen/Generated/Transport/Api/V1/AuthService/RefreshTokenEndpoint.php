<?php

declare(strict_types=1);

namespace App\Generated\Transport\Api\V1\AuthService;

use App\Api\V1\RefreshTokenRequest;
use App\Api\V1\RefreshTokenResponse;
use Psr\Http\Message\ServerRequestInterface;

interface RefreshTokenEndpoint
{
    function handle(RefreshTokenRequest $request, ServerRequestInterface $httpRequest): RefreshTokenResponse;
}

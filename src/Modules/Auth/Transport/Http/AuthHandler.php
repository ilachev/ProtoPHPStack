<?php

declare(strict_types=1);

namespace App\Modules\Auth\Transport\Http;

use App\Platform\Http\Handler\AbstractJsonHandler;
use App\Platform\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Authentication handler stub.
 * TODO: Implement actual authentication logic.
 */
final readonly class AuthHandler extends AbstractJsonHandler
{
    public function __construct(
        JsonResponse $jsonResponse,
    ) {
        parent::__construct($jsonResponse);
    }

    /**
     * @throws \JsonException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // TODO: Implement authentication endpoints (login, logout, refresh)
        return $this->jsonResponse('{"error":"Not implemented"}', 501);
    }
}

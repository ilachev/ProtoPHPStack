<?php

declare(strict_types=1);

namespace App\Generated\Transport\Api\V1\AuthService;

use App\Api\V1\RefreshTokenRequest;
use App\Api\V1\RefreshTokenResponse;
use App\Platform\Http\Handler\AbstractProtobufRpcHandler;
use App\Platform\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class RefreshTokenHttpHandler extends AbstractProtobufRpcHandler
{
    public function __construct(
        private RefreshTokenEndpoint $endpoint,
        JsonResponse $jsonResponse,
    ) {
        parent::__construct($jsonResponse);
    }

    /**
     * @throws \JsonException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $message = $this->decodeRequest($request, RefreshTokenRequest::class);
        if (!$message instanceof RefreshTokenRequest) {
            return $this->invalidRequestResponse();
        }

        /** @var RefreshTokenResponse $response */
        $response = $this->endpoint->handle($message, $request);

        return $this->protobufResponse($response);
    }
}

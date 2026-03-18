<?php

declare(strict_types=1);

namespace App\Generated\Transport\Api\V1\AuthService;

use App\Api\V1\LoginRequest;
use App\Api\V1\LoginResponse;
use App\Platform\Http\Handler\AbstractProtobufRpcHandler;
use App\Platform\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class LoginHttpHandler extends AbstractProtobufRpcHandler
{
    public function __construct(
        private LoginEndpoint $endpoint,
        JsonResponse $jsonResponse,
    ) {
        parent::__construct($jsonResponse);
    }

    /**
     * @throws \JsonException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $message = $this->decodeRequest($request, LoginRequest::class);
        if (!$message instanceof LoginRequest) {
            return $this->invalidRequestResponse();
        }

        /** @var LoginResponse $response */
        $response = $this->endpoint->handle($message, $request);

        return $this->protobufResponse($response);
    }
}

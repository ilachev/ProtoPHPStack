<?php

declare(strict_types=1);

namespace App\Generated\Transport\Api\V1\AuthService;

use App\Api\V1\LogoutRequest;
use App\Api\V1\LogoutResponse;
use App\Platform\Http\Handler\AbstractProtobufRpcHandler;
use App\Platform\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class LogoutHttpHandler extends AbstractProtobufRpcHandler
{
    public function __construct(
        private LogoutEndpoint $endpoint,
        JsonResponse $jsonResponse,
    ) {
        parent::__construct($jsonResponse);
    }

    /**
     * @throws \JsonException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $message = $this->decodeRequest($request, LogoutRequest::class);
        if (!$message instanceof LogoutRequest) {
            return $this->invalidRequestResponse();
        }

        /** @var LogoutResponse $response */
        $response = $this->endpoint->handle($message, $request);

        return $this->protobufResponse($response);
    }
}

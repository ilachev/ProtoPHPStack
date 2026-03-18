<?php

declare(strict_types=1);

namespace App\Generated\Transport\Api\V1\HomeService;

use App\Api\V1\HomeRequest;
use App\Api\V1\HomeResponse;
use App\Platform\Http\Handler\AbstractProtobufRpcHandler;
use App\Platform\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class HomeHttpHandler extends AbstractProtobufRpcHandler
{
    public function __construct(
        private HomeEndpoint $endpoint,
        JsonResponse $jsonResponse,
    ) {
        parent::__construct($jsonResponse);
    }

    /**
     * @throws \JsonException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $message = $this->decodeRequest($request, HomeRequest::class);
        if (!$message instanceof HomeRequest) {
            return $this->invalidRequestResponse();
        }

        /** @var HomeResponse $response */
        $response = $this->endpoint->handle($message, $request);

        return $this->protobufResponse($response);
    }
}

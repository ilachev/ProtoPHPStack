<?php

declare(strict_types=1);

namespace App\Generated\Endpoint\Api\V1\HealthService;

use App\Api\V1\HealthCheckRequest;
use App\Api\V1\HealthCheckResponse;
use App\Platform\Http\Handler\AbstractProtobufRpcHandler;
use App\Platform\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class CheckHttpHandler extends AbstractProtobufRpcHandler
{
    public function __construct(
        private CheckEndpoint $endpoint,
        JsonResponse $jsonResponse,
    ) {
        parent::__construct($jsonResponse);
    }

    /**
     * @throws \JsonException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $message = $this->decodeRequest($request, HealthCheckRequest::class);
        if (!$message instanceof HealthCheckRequest) {
            return $this->invalidRequestResponse();
        }

        /** @var HealthCheckResponse $response */
        $response = $this->endpoint->handle($message, $request);

        return $this->protobufResponse($response);
    }
}

<?php

declare(strict_types=1);

namespace App\Generated\Transport\Api\V1\SystemService;

use App\Api\V1\SystemDescribeRequest;
use App\Api\V1\SystemDescribeResponse;
use App\Platform\Http\Handler\AbstractProtobufRpcHandler;
use App\Platform\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class DescribeHttpHandler extends AbstractProtobufRpcHandler
{
    public function __construct(
        private DescribeEndpoint $endpoint,
        JsonResponse $jsonResponse,
    ) {
        parent::__construct($jsonResponse);
    }

    /**
     * @throws \JsonException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $message = $this->decodeRequest($request, SystemDescribeRequest::class);
        if (!$message instanceof SystemDescribeRequest) {
            return $this->invalidRequestResponse();
        }

        /** @var SystemDescribeResponse $response */
        $response = $this->endpoint->handle($message, $request);

        return $this->protobufResponse($response);
    }
}

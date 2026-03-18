<?php

declare(strict_types=1);

namespace App\Platform\Http\Handler;

use App\Api\V1\HealthCheckResponse;
use App\Platform\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class HealthCheckHandler extends AbstractJsonHandler
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
        $response = (new HealthCheckResponse())
            ->setStatus('ok')
            ->setTimestamp(time());

        return $this->jsonResponse($response->serializeToJsonString());
    }
}

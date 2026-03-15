<?php

declare(strict_types=1);

namespace App\Modules\Home\Transport\Http;

use App\Modules\Home\Domain\HomeService;
use App\Modules\Home\Transport\Mapping\HomeResponseMapper;
use App\Platform\Http\Handler\AbstractJsonHandler;
use App\Platform\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class HomeHandler extends AbstractJsonHandler
{
    public function __construct(
        private HomeService $homeService,
        private HomeResponseMapper $homeResponseMapper,
        JsonResponse $jsonResponse,
    ) {
        parent::__construct($jsonResponse);
    }

    /**
     * @throws \JsonException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $message = $this->homeService->getWelcomeMessage();
        $response = $this->homeResponseMapper->toResponse($message);

        return $this->jsonResponse($response->serializeToJsonString());
    }
}

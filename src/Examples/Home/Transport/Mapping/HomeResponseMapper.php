<?php

declare(strict_types=1);

namespace App\Examples\Home\Transport\Mapping;

use App\Api\V1\HomeData;
use App\Api\V1\HomeResponse;
use App\Application\Mappers\DataTransferObjectMapper;

final readonly class HomeResponseMapper
{
    public function __construct(
        private DataTransferObjectMapper $dtoMapper,
    ) {}

    public function toResponse(string $message): HomeResponse
    {
        return $this->dtoMapper->toResponse(
            HomeData::class,
            HomeResponse::class,
            ['message' => $message],
        );
    }
}

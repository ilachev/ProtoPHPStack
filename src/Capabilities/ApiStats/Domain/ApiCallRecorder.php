<?php

declare(strict_types=1);

namespace App\Capabilities\ApiStats\Domain;

final readonly class ApiCallRecorder
{
    public function __construct(
        private ApiStatRepository $repository,
    ) {}

    public function record(ApiStat $stat): void
    {
        $this->repository->save($stat);
    }
}

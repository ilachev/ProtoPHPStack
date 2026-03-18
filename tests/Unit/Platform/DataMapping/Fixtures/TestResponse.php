<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\DataMapping\Fixtures;

use App\Api\V1\HealthCheckResponse;
use App\Platform\DataMapping\ResponseDataContainer;

final class TestResponse implements ResponseDataContainer
{
    private ?HealthCheckResponse $data = null;

    public function setData(object $data): self
    {
        if (!$data instanceof HealthCheckResponse) {
            throw new \InvalidArgumentException('Expected HealthCheckResponse data.');
        }

        $this->data = $data;

        return $this;
    }

    public function getData(): ?HealthCheckResponse
    {
        return $this->data;
    }
}

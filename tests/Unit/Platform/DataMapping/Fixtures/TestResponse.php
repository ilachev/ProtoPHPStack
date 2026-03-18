<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\DataMapping\Fixtures;

use App\Api\V1\HealthCheckResponse;

final class TestResponse
{
    private ?HealthCheckResponse $data = null;

    public function setData(HealthCheckResponse $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function getData(): ?HealthCheckResponse
    {
        return $this->data;
    }
}

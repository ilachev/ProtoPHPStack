<?php

declare(strict_types=1);

namespace App\Platform\DataMapping;

interface ResponseDataContainer
{
    public function setData(object $data): self;
}

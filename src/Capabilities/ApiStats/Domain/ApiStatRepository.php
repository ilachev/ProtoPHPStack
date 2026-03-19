<?php

declare(strict_types=1);

namespace App\Capabilities\ApiStats\Domain;

interface ApiStatRepository
{
    public function save(ApiStat $stat): ApiStat;
}

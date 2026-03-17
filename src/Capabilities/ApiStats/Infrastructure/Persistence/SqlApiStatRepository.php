<?php

declare(strict_types=1);

namespace App\Capabilities\ApiStats\Infrastructure\Persistence;

use App\Capabilities\ApiStats\Domain\ApiStat;
use App\Capabilities\ApiStats\Domain\ApiStatRepository;
use App\Platform\Storage\Repository\AbstractRepository;

final class SqlApiStatRepository extends AbstractRepository implements ApiStatRepository
{
    private const TABLE_NAME = 'api_stats';

    public function save(ApiStat $stat): void
    {
        $this->saveEntity($stat, self::TABLE_NAME, 'id', $stat->id);
    }
}

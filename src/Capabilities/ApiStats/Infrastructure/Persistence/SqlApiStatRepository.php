<?php

declare(strict_types=1);

namespace App\Capabilities\ApiStats\Infrastructure\Persistence;

use App\Capabilities\ApiStats\Domain\ApiStat;
use App\Capabilities\ApiStats\Domain\ApiStatRepository;
use App\Platform\Storage\Repository\AbstractRepository;

final class SqlApiStatRepository extends AbstractRepository implements ApiStatRepository
{
    private const TABLE_NAME = 'api_stats';

    /**
     * @return array<ApiStat>
     */
    public function findBySessionId(string $sessionId, int $limit = 100, int $offset = 0): array
    {
        $query = $this->query(self::TABLE_NAME)
            ->where('session_id', $sessionId)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset);

        return $this->fetchAll(ApiStat::class, $query);
    }

    /**
     * @return array<ApiStat>
     */
    public function findByRoute(string $route, int $limit = 100, int $offset = 0): array
    {
        $query = $this->query(self::TABLE_NAME)
            ->where('route', $route)
            ->orderBy('request_time', 'DESC')
            ->limit($limit)
            ->offset($offset);

        return $this->fetchAll(ApiStat::class, $query);
    }

    /**
     * @return array<ApiStat>
     */
    public function findBySessionAndRoute(string $sessionId, string $route, int $limit = 100, int $offset = 0): array
    {
        $query = $this->query(self::TABLE_NAME)
            ->where('session_id', $sessionId)
            ->where('route', $route)
            ->orderBy('request_time', 'DESC')
            ->limit($limit)
            ->offset($offset);

        return $this->fetchAll(ApiStat::class, $query);
    }

    /**
     * @return array<ApiStat>
     */
    public function findByMethod(string $method, int $limit = 100, int $offset = 0): array
    {
        $query = $this->query(self::TABLE_NAME)
            ->where('method', $method)
            ->orderBy('request_time', 'DESC')
            ->limit($limit)
            ->offset($offset);

        return $this->fetchAll(ApiStat::class, $query);
    }

    /**
     * @return array<ApiStat>
     */
    public function findByTimeRange(int $startTimestamp, int $endTimestamp, int $limit = 100, int $offset = 0): array
    {
        $query = $this->query(self::TABLE_NAME)
            ->where('request_time', $startTimestamp, '>=')
            ->where('request_time', $endTimestamp, '<=')
            ->orderBy('request_time', 'DESC')
            ->limit($limit)
            ->offset($offset);

        return $this->fetchAll(ApiStat::class, $query);
    }

    public function save(ApiStat $stat): void
    {
        $this->saveEntity($stat, self::TABLE_NAME, 'id', $stat->id);
    }
}

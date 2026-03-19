<?php

declare(strict_types=1);

namespace App\Capabilities\ApiStats\Infrastructure\Persistence;

use App\Capabilities\ApiStats\Domain\ApiStat;
use App\Capabilities\ApiStats\Domain\ApiStatRepository;
use App\Generated\Sql\ApiStats\InsertApiStatQuery;
use App\Platform\Storage\Sql\SqlExecutor;

final readonly class SqlApiStatRepository implements ApiStatRepository
{
    public function __construct(
        private SqlExecutor $sqlExecutor,
    ) {}

    public function save(ApiStat $stat): void
    {
        $this->sqlExecutor->execute(
            InsertApiStatQuery::create(
                sessionId: $stat->sessionId,
                route: $stat->route,
                method: $stat->method,
                statusCode: $stat->statusCode,
                executionTime: $stat->executionTime,
                requestTime: $stat->requestTime,
            ),
        );
    }
}

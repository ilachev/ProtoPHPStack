<?php

declare(strict_types=1);

namespace App\Platform\Storage\Query;

final readonly class SqlQuery
{
    /**
     * @param array<string, mixed> $params
     */
    public function __construct(
        public string $sql,
        public array $params,
    ) {}
}

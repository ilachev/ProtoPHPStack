<?php

declare(strict_types=1);

namespace App\Platform\Storage\Query;

interface QueryFactory
{
    public function createQueryBuilder(string $table): QueryBuilder;
}

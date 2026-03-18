<?php

declare(strict_types=1);

namespace App\Generated\Sql\Session;

final readonly class DeleteExpiredSessionsParams
{
    public function __construct(
        public string|int|float|bool|null $now,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Platform\Storage\Sql;

/**
 * @template TParams of array<string, scalar|null>
 */
interface ExecutableQuery
{
    public function sql(): string;

    /**
     * @return TParams
     */
    public function params(): array;
}

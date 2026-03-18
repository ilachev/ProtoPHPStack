<?php

declare(strict_types=1);

namespace App\Platform\Storage\Sql;

interface ExecutableQuery
{
    public function name(): string;

    public function resultKind(): QueryResultKind;

    public function sql(): string;

    /**
     * @return array<string, scalar|null>
     */
    public function params(): array;
}

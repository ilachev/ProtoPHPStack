<?php

declare(strict_types=1);

namespace App\Platform\Storage\Migration;

abstract readonly class AbstractMigration implements Migration
{
    final public function getVersion(): string
    {
        return basename(str_replace('\\', '/', static::class));
    }
}

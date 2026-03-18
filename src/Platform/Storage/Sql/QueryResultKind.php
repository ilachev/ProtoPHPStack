<?php

declare(strict_types=1);

namespace App\Platform\Storage\Sql;

enum QueryResultKind: string
{
    case One = 'one';
    case Many = 'many';
    case Exec = 'exec';
}

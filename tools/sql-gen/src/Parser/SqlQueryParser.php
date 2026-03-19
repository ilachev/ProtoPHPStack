<?php

declare(strict_types=1);

namespace SqlGen\Parser;

use SqlGen\Ast\SqlQuery;

interface SqlQueryParser
{
    public function parse(string $sql): SqlQuery;
}

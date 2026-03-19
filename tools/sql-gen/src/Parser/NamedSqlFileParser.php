<?php

declare(strict_types=1);

namespace SqlGen\Parser;

use SqlGen\Model\SqlFile;

interface NamedSqlFileParser
{
    public function parseFile(string $path): SqlFile;
}

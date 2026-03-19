<?php

declare(strict_types=1);

namespace SqlGen\Schema;

use SqlGen\Model\DatabaseSchema;

interface DatabaseSchemaParser
{
    public function parseFile(string $path): DatabaseSchema;
}

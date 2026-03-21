<?php

declare(strict_types=1);

namespace SqlGen\Model;

use SqlGen\Type\PhpTypeFactory;
use Typhoon\Type;

final readonly class ResolvedSqlParameter
{
    public Type $phpType;

    public function __construct(
        public string $name,
        public string $propertyName,
        public string $sqlType,
        string|Type $phpType,
        public bool $nullable,
    ) {
        $this->phpType = PhpTypeFactory::fromNativeType($phpType);
    }
}

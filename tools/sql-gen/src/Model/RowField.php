<?php

declare(strict_types=1);

namespace SqlGen\Model;

use SqlGen\Type\PhpTypeFactory;
use Typhoon\Type;

final readonly class RowField
{
    public Type $phpType;

    public function __construct(
        public string $sourceColumnName,
        public string $resultColumnName,
        public string $propertyName,
        string|Type $phpType,
        public bool $nullable,
    ) {
        $this->phpType = PhpTypeFactory::fromNativeType($phpType);
    }
}

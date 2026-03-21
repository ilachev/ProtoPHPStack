<?php

declare(strict_types=1);

namespace SqlGen\Model;

use SqlGen\Type\PhpTypeFactory;
use Typhoon\Type;

final readonly class SchemaColumn
{
    public Type $phpType;

    public function __construct(
        public string $name,
        public string $sqlType,
        string|Type $phpType,
        public bool $nullable,
        public bool $primaryKey = false,
        public bool $unique = false,
        public ?SchemaTableReference $reference = null,
    ) {
        $this->phpType = PhpTypeFactory::fromNativeType($phpType);
    }
}

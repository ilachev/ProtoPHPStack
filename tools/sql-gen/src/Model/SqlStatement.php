<?php

declare(strict_types=1);

namespace SqlGen\Model;

final readonly class SqlStatement
{
    /**
     * @param list<SqlParameter> $parameters
     */
    public function __construct(
        public string $name,
        public SqlResultKind $resultKind,
        public string $sql,
        public array $parameters,
    ) {}

    public function getParamsClassName(): string
    {
        return $this->name . 'Params';
    }

    public function getQueryClassName(): string
    {
        return $this->name . 'Query';
    }

    public function getRowClassName(): string
    {
        return $this->name . 'Row';
    }

    public function getFactoryMethodName(): string
    {
        return lcfirst($this->name);
    }
}

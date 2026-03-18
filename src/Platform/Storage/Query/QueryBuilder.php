<?php

declare(strict_types=1);

namespace App\Platform\Storage\Query;

interface QueryBuilder
{
    /**
     * @param string|array<string> $columns
     */
    public function select(string|array $columns): static;

    public function where(string $column, mixed $value, string $operator = '='): static;

    /**
     * @param array<mixed> $values
     */
    public function whereIn(string $column, array $values): static;

    /**
     * @param array<string, mixed> $params
     */
    public function whereRaw(string $condition, array $params = []): static;

    public function orderBy(string $column, string $direction = 'ASC'): static;

    public function limit(int $limit): static;

    public function offset(int $offset): static;

    public function buildSelectQuery(): SqlQuery;

    /**
     * @param array<string, mixed> $data
     */
    public function buildInsertQuery(array $data): SqlQuery;

    /**
     * @param array<string, mixed> $data
     * @param string $primaryKey Primary key column name for the UPSERT operation
     */
    public function buildUpsertQuery(array $data, string $primaryKey): SqlQuery;

    /**
     * @param array<string, mixed> $data
     */
    public function buildUpdateQuery(array $data): SqlQuery;

    public function buildDeleteQuery(): SqlQuery;
}

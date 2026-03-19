<?php

declare(strict_types=1);

namespace App\Generated\Sql\Session;

use App\Platform\Storage\Sql\ExecutableQuery;
use App\Platform\Storage\Sql\QueryResultKind;

final readonly class FindSessionsByUserIdQuery implements ExecutableQuery
{
    public function __construct(
        private FindSessionsByUserIdParams $params,
    ) {
    }

    public function name(): string
    {
        return 'FindSessionsByUserId';
    }

    public function resultKind(): QueryResultKind
    {
        return QueryResultKind::from('many');
    }

    public function rowClass(): string
    {
        return 'App\Generated\Sql\Session\FindSessionsByUserIdRow';
    }

    public function sql(): string
    {
        return <<<'SQL'
        SELECT id, user_id, payload, expires_at, created_at, updated_at
        FROM sessions
        WHERE user_id = :user_id
        ORDER BY created_at DESC;
        SQL;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function params(): array
    {
        return [
            'user_id' => $this->params->user_id,
        ];
    }
}

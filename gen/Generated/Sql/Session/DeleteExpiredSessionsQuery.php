<?php

declare(strict_types=1);

namespace App\Generated\Sql\Session;

use App\Platform\Storage\Sql\ExecutableQuery;
use App\Platform\Storage\Sql\QueryResultKind;

final readonly class DeleteExpiredSessionsQuery implements ExecutableQuery
{
    public function __construct(
        private DeleteExpiredSessionsParams $params,
    ) {
    }

    public function name(): string
    {
        return 'DeleteExpiredSessions';
    }

    public function resultKind(): QueryResultKind
    {
        return QueryResultKind::from('exec');
    }

    public function sql(): string
    {
        return <<<'SQL'
        DELETE FROM sessions
        WHERE expires_at < :now;
        SQL;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function params(): array
    {
        return [
            'now' => $this->params->now,
        ];
    }
}

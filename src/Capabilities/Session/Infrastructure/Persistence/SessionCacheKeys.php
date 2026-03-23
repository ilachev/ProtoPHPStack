<?php

declare(strict_types=1);

namespace App\Capabilities\Session\Infrastructure\Persistence;

use App\Platform\Cache\CacheKey;
use App\Platform\Cache\CacheScope;

final readonly class SessionCacheKeys
{
    public function session(string $sessionId): CacheKey
    {
        return $this->sessionScope()->key($sessionId);
    }

    public function userSessions(int $userId): CacheKey
    {
        return $this->userSessionsScope()->key($userId);
    }

    private function sessionScope(): CacheScope
    {
        return new CacheScope('session');
    }

    private function userSessionsScope(): CacheScope
    {
        return new CacheScope('session_user');
    }
}

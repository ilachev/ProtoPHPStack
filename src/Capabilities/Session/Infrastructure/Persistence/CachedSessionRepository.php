<?php

declare(strict_types=1);

namespace App\Capabilities\Session\Infrastructure\Persistence;

use App\Capabilities\Session\Domain\Session;
use App\Capabilities\Session\Domain\SessionRepository;
use App\Platform\Cache\ScopedCache;
use App\Platform\Cache\ScopedCacheFactory;
use App\Platform\Logging\Logger;
use App\Platform\Storage\Repository\AbstractCachedRepository;

final readonly class CachedSessionRepository extends AbstractCachedRepository implements SessionRepository
{
    private ScopedCache $sessionCache;

    private ScopedCache $userSessionsCache;

    public function __construct(
        private SessionRepository $repository,
        ScopedCacheFactory $scopedCacheFactory,
        Logger $logger,
    ) {
        parent::__construct(
            logger: $logger,
        );

        $this->sessionCache = $scopedCacheFactory->scope('session');
        $this->userSessionsCache = $scopedCacheFactory->scope('session_user');
    }

    public function findById(string $id): ?Session
    {
        /** @var ?Session */
        return $this->getOrSetCacheValue($this->sessionCache, $id, fn() => $this->repository->findById($id));
    }

    public function findByUserId(int $userId): array
    {
        /** @var array<Session> */
        return $this->getOrSetCacheValue($this->userSessionsCache, $userId, fn() => $this->repository->findByUserId($userId));
    }

    public function findAll(): array
    {
        return $this->repository->findAll();
    }

    public function save(Session $session): Session
    {
        $persistedSession = $this->repository->save($session);

        $this->setCacheValue($this->sessionCache, $persistedSession->id, $persistedSession);

        if ($persistedSession->userId !== null) {
            $this->deleteCacheValue($this->userSessionsCache, $persistedSession->userId);
        }

        return $persistedSession;
    }

    public function delete(string $id): void
    {
        $session = $this->repository->findById($id);
        $this->repository->delete($id);

        $this->deleteCacheValue($this->sessionCache, $id);

        if ($session !== null && $session->userId !== null) {
            $this->deleteCacheValue($this->userSessionsCache, $session->userId);
        }
    }

    public function deleteExpired(): void
    {
        $this->repository->deleteExpired();
    }
}

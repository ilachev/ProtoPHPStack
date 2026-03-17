<?php

declare(strict_types=1);

namespace App\Capabilities\Session\Infrastructure\Persistence;

use App\Capabilities\Session\Domain\Session;
use App\Capabilities\Session\Domain\SessionRepository;
use App\Platform\Cache\CacheService;
use App\Platform\Logging\Logger;
use App\Platform\Storage\Repository\AbstractCachedRepository;

final readonly class CachedSessionRepository extends AbstractCachedRepository implements SessionRepository
{
    private const string CACHE_KEY_PREFIX = 'session:';
    private const string CACHE_USER_PREFIX = 'session_user:';

    public function __construct(
        private SessionRepository $repository,
        CacheService $cache,
        Logger $logger,
    ) {
        parent::__construct(
            cache: $cache,
            logger: $logger,
        );
    }

    public function findById(string $id): ?Session
    {
        $cacheKey = $this->getSessionCacheKey($id);

        /** @var ?Session */
        return $this->getOrSetCacheValue($cacheKey, fn() => $this->repository->findById($id));
    }

    public function findByUserId(int $userId): array
    {
        $cacheKey = $this->getUserSessionsCacheKey($userId);

        /** @var array<Session> */
        return $this->getOrSetCacheValue($cacheKey, fn() => $this->repository->findByUserId($userId));
    }

    public function findAll(): array
    {
        return $this->repository->findAll();
    }

    public function save(Session $session): void
    {
        $this->repository->save($session);

        $sessionCacheKey = $this->getSessionCacheKey($session->id);
        $this->setCacheValue($sessionCacheKey, $session);

        if ($session->userId !== null) {
            $userCacheKey = $this->getUserSessionsCacheKey($session->userId);
            $this->deleteCacheValue($userCacheKey);
        }
    }

    public function delete(string $id): void
    {
        $session = $this->repository->findById($id);
        $this->repository->delete($id);

        $sessionCacheKey = $this->getSessionCacheKey($id);
        $this->deleteCacheValue($sessionCacheKey);

        if ($session !== null && $session->userId !== null) {
            $userCacheKey = $this->getUserSessionsCacheKey($session->userId);
            $this->deleteCacheValue($userCacheKey);
        }
    }

    public function deleteExpired(): void
    {
        $this->repository->deleteExpired();
    }

    private function getSessionCacheKey(string $id): string
    {
        return self::CACHE_KEY_PREFIX . $id;
    }

    private function getUserSessionsCacheKey(int $userId): string
    {
        return self::CACHE_USER_PREFIX . $userId;
    }
}

<?php

declare(strict_types=1);

namespace App\Platform\Console;

use App\Platform\Cache\CacheService;
use App\Platform\Logging\Logger;

final readonly class CacheClearCommand
{
    public function __construct(
        private CacheService $cacheService,
        private Logger $logger,
    ) {}

    /**
     * Invalidates the active cache namespace.
     *
     * @param bool $quiet When true, suppresses console output
     * @return bool True on success, false on failure
     */
    public function clear(bool $quiet = false): bool
    {
        try {
            $success = $this->cacheService->clear();

            if ($success) {
                $this->logger->info('Cache namespace invalidated successfully via console command');
                if (!$quiet) {
                    echo "Cache namespace invalidated successfully.\n";
                }

                return true;
            }

            $this->logger->warning('Cache namespace invalidation reported failure without throwing exception');
            if (!$quiet) {
                echo "Warning: Cache namespace invalidation completed but reported failure.\n";
            }

            return false;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to invalidate cache namespace', [
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            if (!$quiet) {
                echo "Error: Failed to invalidate cache namespace: {$e->getMessage()}\n";
            }

            return false;
        }
    }
}

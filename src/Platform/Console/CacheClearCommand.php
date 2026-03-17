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
     * Clears the cache backend.
     *
     * @param bool $quiet When true, suppresses console output
     * @return bool True on success, false on failure
     */
    public function clear(bool $quiet = false): bool
    {
        try {
            $success = $this->cacheService->clear();

            if ($success) {
                $this->logger->info('Cache cleared successfully via console command');
                if (!$quiet) {
                    echo "Cache cleared successfully.\n";
                }

                return true;
            }

            $this->logger->warning('Cache clear reported failure without throwing exception');
            if (!$quiet) {
                echo "Warning: Cache clearing completed but reported failure.\n";
            }

            return false;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to clear cache', [
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            if (!$quiet) {
                echo "Error: Failed to clear cache: {$e->getMessage()}\n";
            }

            return false;
        }
    }
}

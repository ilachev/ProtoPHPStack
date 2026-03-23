<?php

declare(strict_types=1);

namespace App\Platform\Cache;

use App\Platform\Logging\Logger;
use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\KeyValue\Factory;
use Spiral\RoadRunner\KeyValue\StorageInterface;

final class RoadRunnerCacheService implements CacheService
{
    private StorageInterface $storage;

    private bool $available = false;

    private bool $degraded = false;

    /**
     * Flag indicating that cache clear operation is in progress.
     */
    private bool $clearInProgress = false;

    public function __construct(
        private readonly CacheConfig $config,
        private readonly Logger $logger,
    ) {
        try {
            if ($this->isTestingEnvironment()) {
                $this->activateFallbackStorage('test environment');

                return;
            }

            $address = $this->config->address !== '' ? $this->config->address : 'tcp://127.0.0.1:6001';
            $rpc = RPC::create($address);
            $factory = new Factory($rpc);
            $engine = $this->config->engine !== '' ? $this->config->engine : 'local-memory';

            try {
                $storage = $factory->select($engine);
                $storage->has('cache_test_key');

                $this->storage = $storage;
                $this->available = true;
                $this->logger->info('KV storage is available');
            } catch (\Throwable $e) {
                $this->activateFallbackStorage('KV storage is not available', $e);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed to initialize cache service: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            $this->activateFallbackStorage('cache service initialization failed', $e);
        }
    }

    private function isTestingEnvironment(): bool
    {
        return \defined('PHPUNIT_COMPOSER_INSTALL') || \defined('__PHPUNIT_PHAR__')
            || isset($_SERVER['ENVIRONMENT']) && $_SERVER['ENVIRONMENT'] === 'testing';
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        if (!$this->available) {
            return false;
        }

        $prefixedKey = $this->prefixKey($key);
        $ttl ??= $this->config->defaultTtl;

        try {
            $this->storage->set($prefixedKey, $value, $ttl);

            return true;
        } catch (\Throwable $e) {
            $this->switchToFallback($e, 'Cache set error', ['key' => $prefixedKey]);

            return false;
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->available) {
            return $default;
        }

        $prefixedKey = $this->prefixKey($key);

        try {
            $value = $this->storage->get($prefixedKey);

            return $value ?? $default;
        } catch (\Throwable $e) {
            $this->switchToFallback($e, 'Cache get error', ['key' => $prefixedKey]);

            return $default;
        }
    }

    public function has(string $key): bool
    {
        if (!$this->available) {
            return false;
        }

        $prefixedKey = $this->prefixKey($key);

        try {
            return $this->storage->has($prefixedKey);
        } catch (\Throwable $e) {
            $this->switchToFallback($e, 'Cache has error', ['key' => $prefixedKey]);

            return false;
        }
    }

    public function delete(string $key): bool
    {
        if (!$this->available) {
            return false;
        }

        $prefixedKey = $this->prefixKey($key);

        try {
            return $this->storage->delete($prefixedKey);
        } catch (\Throwable $e) {
            $this->switchToFallback($e, 'Cache delete error', ['key' => $prefixedKey]);

            return false;
        }
    }

    public function clear(): bool
    {
        if (!$this->available || $this->clearInProgress) {
            $this->logger->debug('Cache clear skipped', [
                'reason' => !$this->available ? 'cache unavailable' : 'already in progress',
            ]);

            return false;
        }

        $this->clearInProgress = true;

        try {
            $maxRetries = 3;
            $retryCount = 0;
            $success = false;

            while (!$success && $retryCount < $maxRetries) {
                try {
                    $this->storage->clear();
                    $success = true;
                } catch (\Throwable $e) {
                    ++$retryCount;
                    if ($retryCount >= $maxRetries) {
                        throw $e;
                    }

                    $this->logger->warning('Cache clear retry', [
                        'attempt' => $retryCount,
                        'error' => $e->getMessage(),
                    ]);

                    usleep($retryCount * 50000);
                }
            }

            $this->logger->info('Cache cleared successfully', [
                'attempts' => $retryCount + 1,
                'degraded' => $this->degraded,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->switchToFallback($e, 'Cache clear error');

            return false;
        } finally {
            $this->clearInProgress = false;
        }
    }

    public function getOrSet(string $key, callable $callback, ?int $ttl = null): mixed
    {
        if (!$this->available) {
            return $callback();
        }

        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    private function prefixKey(string $key): string
    {
        $namespaceSeed = $this->config->namespaceSeed !== '' ? $this->config->namespaceSeed . ':' : '';

        return $this->config->defaultPrefix . $namespaceSeed . $key;
    }

    private function activateFallbackStorage(string $reason, ?\Throwable $exception = null): void
    {
        $this->storage = new FallbackStorage($this->config->fallbackMaxEntries);
        $this->available = true;
        $this->degraded = true;

        $context = ['reason' => $reason];
        if ($exception !== null) {
            $context['error'] = $exception->getMessage();
            $context['exception'] = $exception;
        }

        $this->logger->warning('Cache switched to in-memory fallback', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function switchToFallback(\Throwable $exception, string $message, array $context = []): void
    {
        $this->logger->error($message . ': ' . $exception->getMessage(), $context + [
            'exception' => $exception,
            'degraded' => $this->degraded,
        ]);

        if ($this->degraded) {
            $this->available = false;

            return;
        }

        $this->activateFallbackStorage($message, $exception);
    }
}

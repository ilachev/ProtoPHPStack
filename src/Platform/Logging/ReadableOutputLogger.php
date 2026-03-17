<?php

declare(strict_types=1);

namespace App\Platform\Logging;

final class ReadableOutputLogger implements Logger
{
    /**
     * @param array<mixed> $context
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $formattedMessage = $this->format($level, $message, $context);
        $this->write($formattedMessage);
    }

    /**
     * @param array<mixed> $context
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    /**
     * @param array<mixed> $context
     */
    public function alert(string $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    /**
     * @param array<mixed> $context
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /**
     * @param array<mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * @param array<mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * @param array<mixed> $context
     */
    public function notice(string $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    /**
     * @param array<mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * @param array<mixed> $context
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    private function write(string $message): void
    {
        file_put_contents('php://stderr', $message);
    }

    /**
     * @param array<mixed> $context
     */
    private function format(string $level, string $message, array $context = []): string
    {
        return \sprintf('[php %s] %s %s', $level, $message, $this->formatContext($context));
    }

    /**
     * @param array<mixed> $context
     */
    private function formatContext(array $context): string
    {
        try {
            return json_encode($context, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return print_r($context, true);
        }
    }
}

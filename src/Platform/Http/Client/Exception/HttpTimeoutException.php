<?php

declare(strict_types=1);

namespace App\Platform\Http\Client\Exception;

use App\Platform\Http\Client\HttpRequest;

final class HttpTimeoutException extends HttpTransportException
{
    public static function forBudgetExhaustion(HttpRequest $request, int $attempt): self
    {
        return new self($request, $attempt, 'HTTP request deadline budget was exhausted');
    }

    public static function forTransportTimeout(
        HttpRequest $request,
        int $attempt,
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
    ): self {
        return new self($request, $attempt, $message, $code, $previous);
    }
}

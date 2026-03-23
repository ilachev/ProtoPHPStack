<?php

declare(strict_types=1);

namespace App\Platform\Http\Client\Exception;

use App\Platform\Http\Client\HttpRequest;

final class CurlTransportException extends HttpTransportException
{
    public static function forCurlFailure(
        HttpRequest $request,
        int $attempt,
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
    ): self {
        return new self($request, $attempt, $message, $code, $previous);
    }
}

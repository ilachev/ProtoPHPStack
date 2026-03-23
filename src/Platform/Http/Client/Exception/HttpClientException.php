<?php

declare(strict_types=1);

namespace App\Platform\Http\Client\Exception;

use App\Platform\Http\Client\HttpRequest;

abstract class HttpClientException extends \RuntimeException
{
    public function __construct(
        public readonly HttpRequest $request,
        public readonly int $attempt,
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}

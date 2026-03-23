<?php

declare(strict_types=1);

namespace App\Platform\Http\Client;

final readonly class HttpRequest
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public HttpMethod $method,
        public string $uri,
        public array $headers = [],
        public string $body = '',
        public ?string $upstream = null,
        public HttpRequestOptions $options = new HttpRequestOptions(),
    ) {}
}

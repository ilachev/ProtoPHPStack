<?php

declare(strict_types=1);

namespace App\Platform\Runtime;

final readonly class RequestContextFactory
{
    public function __construct(
        private RequestIdGenerator $requestIdGenerator,
    ) {}

    public function create(float $timeoutSeconds, ?string $requestId = null): RequestContext
    {
        return new RequestContext(
            requestId: $requestId !== null && $requestId !== ''
                ? $requestId
                : $this->requestIdGenerator->generate(),
            deadline: Deadline::fromSeconds($timeoutSeconds),
        );
    }
}

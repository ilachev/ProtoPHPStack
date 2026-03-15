<?php

declare(strict_types=1);

namespace App\Capabilities\Session\Application;

use App\Capabilities\Session\Domain\SessionPayload;
use Psr\Http\Message\ServerRequestInterface;

interface SessionPayloadFactory
{
    public function createFromRequest(ServerRequestInterface $request): SessionPayload;

    public function createDefault(): SessionPayload;
}

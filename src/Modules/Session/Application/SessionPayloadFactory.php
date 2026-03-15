<?php

declare(strict_types=1);

namespace App\Modules\Session\Application;

use App\Modules\Session\Domain\SessionPayload;
use Psr\Http\Message\ServerRequestInterface;

interface SessionPayloadFactory
{
    public function createFromRequest(ServerRequestInterface $request): SessionPayload;

    public function createDefault(): SessionPayload;
}

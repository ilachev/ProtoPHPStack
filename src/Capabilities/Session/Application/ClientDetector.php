<?php

declare(strict_types=1);

namespace App\Capabilities\Session\Application;

use Psr\Http\Message\ServerRequestInterface;

interface ClientDetector
{
    /**
     * @return array<ClientIdentity>
     */
    public function findSimilarClients(ServerRequestInterface $request, bool $includeCurrent = false): array;

    public function isRequestSuspicious(ServerRequestInterface $request): bool;
}

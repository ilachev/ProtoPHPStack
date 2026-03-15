<?php

declare(strict_types=1);

namespace App\Modules\Session\Transport\Http;

final class SessionResponseHeaders
{
    public const ACTIVE_SESSION_ID = 'X-App-Active-Session-Id';
    public const DESTROY_SESSION = 'X-App-Destroy-Session';
}

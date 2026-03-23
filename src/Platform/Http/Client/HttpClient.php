<?php

declare(strict_types=1);

namespace App\Platform\Http\Client;

interface HttpClient
{
    public function send(HttpRequest $request): HttpResponse;
}

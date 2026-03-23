<?php

declare(strict_types=1);

namespace App\Platform\Http\Client;

interface HttpTransport
{
    public function send(HttpRequest $request, Deadline $deadline, int $attempt): HttpResponse;
}

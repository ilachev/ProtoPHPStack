<?php

declare(strict_types=1);

use App\Platform\Http\HttpRuntimeConfig;

return new HttpRuntimeConfig(
    requestTimeoutSeconds: 15.0,
);

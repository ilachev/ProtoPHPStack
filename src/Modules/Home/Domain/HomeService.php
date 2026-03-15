<?php

declare(strict_types=1);

namespace App\Modules\Home\Domain;

final readonly class HomeService
{
    public function getWelcomeMessage(): string
    {
        return 'Welcome to our API';
    }
}

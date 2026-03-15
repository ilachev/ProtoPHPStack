<?php

declare(strict_types=1);

namespace App\Platform\Http\Handler;

interface HandlerFactoryInterface
{
    /**
     * @param class-string<HandlerInterface> $handlerClass
     */
    public function create(string $handlerClass): HandlerInterface;
}

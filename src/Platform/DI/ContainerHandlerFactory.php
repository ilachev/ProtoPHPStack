<?php

declare(strict_types=1);

namespace App\Platform\DI;

use App\Platform\Http\Handler\HandlerFactoryInterface;
use App\Platform\Http\Handler\HandlerInterface;

final readonly class ContainerHandlerFactory implements HandlerFactoryInterface
{
    /**
     * @param Container<object> $container
     */
    public function __construct(
        private Container $container,
    ) {}

    /**
     * @throws ContainerException
     */
    public function create(string $handlerClass): HandlerInterface
    {
        return $this->container->get($handlerClass);
    }
}

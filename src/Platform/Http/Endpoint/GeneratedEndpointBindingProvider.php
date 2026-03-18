<?php

declare(strict_types=1);

namespace App\Platform\Http\Endpoint;

use App\Platform\Http\GeneratedOperationManifestProvider;

final class GeneratedEndpointBindingProvider
{
    /**
     * @var array<class-string, class-string>|null
     */
    private ?array $bindings = null;

    public function __construct(
        private readonly GeneratedOperationManifestProvider $operationProvider,
    ) {}

    /**
     * @return array<class-string, class-string>
     */
    public function getBindings(): array
    {
        if ($this->bindings !== null) {
            return $this->bindings;
        }

        $bindings = [];

        foreach ($this->operationProvider->getOperations() as $operation) {
            /** @var class-string $interface */
            $interface = $operation['endpoint_interface'];
            /** @var class-string $implementation */
            $implementation = $operation['endpoint_implementation'];
            $bindings[$interface] = $implementation;
        }

        ksort($bindings);
        $this->bindings = $bindings;

        return $this->bindings;
    }
}

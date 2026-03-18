<?php

declare(strict_types=1);

namespace App\Platform\Http\Endpoint;

use App\Platform\Http\GeneratedOperationManifestProvider;

final class GeneratedEndpointImplementationMapProvider
{
    /**
     * @var array<class-string, class-string>|null
     */
    private ?array $implementations = null;

    public function __construct(
        private readonly GeneratedOperationManifestProvider $operationProvider,
    ) {}

    /**
     * @return array<class-string, class-string>
     */
    public function getImplementations(): array
    {
        if ($this->implementations !== null) {
            return $this->implementations;
        }

        $implementations = [];

        foreach ($this->operationProvider->getOperations() as $operation) {
            /** @var class-string $interface */
            $interface = $operation->endpointInterface;
            /** @var class-string $implementation */
            $implementation = $operation->endpointImplementation;
            $implementations[$interface] = $implementation;
        }

        ksort($implementations);
        $this->implementations = $implementations;

        return $this->implementations;
    }
}

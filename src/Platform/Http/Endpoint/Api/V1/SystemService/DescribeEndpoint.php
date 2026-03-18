<?php

declare(strict_types=1);

namespace App\Platform\Http\Endpoint\Api\V1\SystemService;

use App\Api\V1\SystemDescribeRequest;
use App\Api\V1\SystemDescribeResponse;
use Psr\Http\Message\ServerRequestInterface;

final readonly class DescribeEndpoint implements \App\Generated\Transport\Api\V1\SystemService\DescribeEndpoint
{
    public function handle(SystemDescribeRequest $request, ServerRequestInterface $httpRequest): SystemDescribeResponse
    {
        $capabilities = [];

        foreach ($request->getRequestedCapabilities() as $capability) {
            if (\is_string($capability) && $capability !== '') {
                $capabilities[] = $capability;
            }
        }

        if ($request->getIncludeRuntime()) {
            $capabilities = array_values(array_unique([...$capabilities, 'http', 'protobuf', 'routing']));
        }

        return (new SystemDescribeResponse())
            ->setName('base-api-template')
            ->setMode('core')
            ->setCaller($request->getCaller())
            ->setCapabilities($capabilities)
            ->setRuntimeIncluded($request->getIncludeRuntime())
            ->setTimestamp(time());
    }
}

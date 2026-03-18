<?php

declare(strict_types=1);

namespace App\Platform\Routing\Generator;

use Google\Api\HttpRule;
use Google\Protobuf\Internal\FileDescriptorProto;
use Google\Protobuf\Internal\FileDescriptorSet;
use Google\Protobuf\Internal\MethodDescriptorProto;
use Google\Protobuf\Internal\ServiceDescriptorProto;

final readonly class ProtoRouteProvider implements RouteProvider
{
    private const GOOGLE_API_HTTP_EXTENSION_FIELD = 72295728;
    private const GENERATED_TRANSPORT_NAMESPACE = 'App\Generated\Transport';

    /**
     * @param string $metadataDir Directory containing generated metadata PHP files
     * @param array<string, string> $handlerMapping Mapping of service.method => handler
     * @param list<string> $sourceFilePrefixes Restrict route extraction to descriptor source file prefixes
     */
    public function __construct(
        private string $metadataDir,
        private array $handlerMapping = [],
        private array $sourceFilePrefixes = [],
    ) {}

    /**
     * @return array<array{
     *     method: string,
     *     path: string,
     *     handler: string,
     *     operation_id?: string
     * }>
     */
    public function getRoutes(): array
    {
        $routes = [];
        $metadataFiles = $this->findMetadataFiles();

        foreach ($metadataFiles as $metadataFile) {
            $descriptorSet = $this->extractDescriptorSet($metadataFile);
            if ($descriptorSet === null) {
                continue;
            }

            /** @var iterable<FileDescriptorProto> $fileDescriptors */
            $fileDescriptors = $descriptorSet->getFile();
            foreach ($fileDescriptors as $fileDescriptor) {
                if (!$this->shouldIncludeDescriptor($fileDescriptor)) {
                    continue;
                }

                $fileNamespace = $this->resolveFileNamespace($fileDescriptor);

                /** @var iterable<ServiceDescriptorProto> $serviceDescriptors */
                $serviceDescriptors = $fileDescriptor->getService();
                foreach ($serviceDescriptors as $serviceDescriptor) {
                    $this->extractRoutesFromServiceDescriptor($serviceDescriptor, $fileNamespace, $routes);
                }
            }
        }

        return $routes;
    }

    /**
     * @return array<string>
     */
    private function findMetadataFiles(): array
    {
        $files = glob("{$this->metadataDir}/*.php");
        if ($files === false || empty($files)) {
            $files = $this->globRecursive("{$this->metadataDir}/*.php");
        }

        return $files;
    }

    /**
     * Recursive glob function that works reliably on different systems.
     *
     * @return array<string>
     */
    private function globRecursive(string $pattern, int $flags = 0): array
    {
        $files = glob($pattern, $flags);
        $files = $files !== false ? $files : [];

        $dirs = glob(\dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT);
        if ($dirs === false) {
            return $files;
        }

        foreach ($dirs as $dir) {
            $moreFiles = $this->globRecursive($dir . '/' . basename($pattern), $flags);
            $files = array_merge($files, $moreFiles);
        }

        return $files;
    }

    /**
     * @param array<array{method: string, path: string, handler: string, operation_id?: string}> &$routes
     */
    private function extractRoutesFromServiceDescriptor(
        ServiceDescriptorProto $serviceDescriptor,
        string $fileNamespace,
        array &$routes,
    ): void {
        $serviceName = $serviceDescriptor->getName();
        if ($serviceName === '') {
            return;
        }

        /** @var iterable<MethodDescriptorProto> $methodDescriptors */
        $methodDescriptors = $serviceDescriptor->getMethod();
        foreach ($methodDescriptors as $methodDescriptor) {
            $methodName = $methodDescriptor->getName();
            if ($methodName === '') {
                continue;
            }

            $httpRule = $this->extractHttpRule($methodDescriptor);
            if ($httpRule === null) {
                continue;
            }

            $operationId = "{$serviceName}.{$methodName}";
            $handler = $this->resolveHandler($serviceName, $methodName, $fileNamespace);

            foreach ($this->expandHttpRuleBindings($httpRule) as $binding) {
                $routes[] = [
                    'method' => $binding['method'],
                    'path' => $binding['path'],
                    'handler' => $handler,
                    'operation_id' => $operationId,
                ];
            }
        }
    }

    private function resolveHandler(string $serviceName, string $methodName, string $fileNamespace): string
    {
        $key = "{$serviceName}.{$methodName}";
        if (isset($this->handlerMapping[$key])) {
            return $this->handlerMapping[$key];
        }

        return $this->resolveGeneratedHandlerClass($serviceName, $methodName, $fileNamespace);
    }

    private function resolveGeneratedHandlerClass(string $serviceName, string $methodName, string $fileNamespace): string
    {
        $suffix = str_starts_with($fileNamespace, 'App\\')
            ? substr($fileNamespace, 4)
            : $fileNamespace;

        return self::GENERATED_TRANSPORT_NAMESPACE . '\\' . $suffix . '\\' . $serviceName . '\\' . $methodName . 'HttpHandler';
    }

    private function shouldIncludeDescriptor(FileDescriptorProto $fileDescriptor): bool
    {
        if ($this->sourceFilePrefixes === []) {
            return true;
        }

        $sourceName = $fileDescriptor->getName();
        if ($sourceName === '') {
            return false;
        }

        foreach ($this->sourceFilePrefixes as $prefix) {
            if (str_starts_with($sourceName, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function extractDescriptorSet(string $metadataFile): ?FileDescriptorSet
    {
        $content = file_get_contents($metadataFile);
        if ($content === false) {
            return null;
        }

        $bytes = $this->extractGeneratedFileBytes($content);
        if ($bytes === null) {
            return null;
        }

        $descriptorSet = new FileDescriptorSet();
        $descriptorSet->mergeFromString($bytes);

        return $descriptorSet;
    }

    private function extractGeneratedFileBytes(string $phpContent): ?string
    {
        $tokens = token_get_all($phpContent);
        $expectStringLiteral = false;

        foreach ($tokens as $token) {
            if (\is_array($token)) {
                if ($token[0] === T_STRING && $token[1] === 'internalAddGeneratedFile') {
                    $expectStringLiteral = true;

                    continue;
                }

                if ($expectStringLiteral && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
                    return $this->decodePhpStringLiteral($token[1]);
                }
            }
        }

        return null;
    }

    private function decodePhpStringLiteral(string $literal): ?string
    {
        if ($literal === '' || $literal[0] !== '"' || !str_ends_with($literal, '"')) {
            return null;
        }

        return stripcslashes(substr($literal, 1, -1));
    }

    private function extractHttpRule(MethodDescriptorProto $methodDescriptor): ?HttpRule
    {
        $options = $methodDescriptor->getOptions();
        if ($options === null) {
            return null;
        }

        $payloads = $this->extractLengthDelimitedFields(
            $options->serializeToString(),
            self::GOOGLE_API_HTTP_EXTENSION_FIELD,
        );

        if ($payloads === []) {
            return null;
        }

        $httpRule = new HttpRule();
        $httpRule->mergeFromString($payloads[0]);

        return $httpRule;
    }

    private function resolveFileNamespace(FileDescriptorProto $fileDescriptor): string
    {
        $options = $fileDescriptor->getOptions();
        if ($options !== null) {
            $phpNamespace = $options->getPhpNamespace();
            if ($phpNamespace !== '') {
                return $phpNamespace;
            }
        }

        $package = $fileDescriptor->getPackage();
        if ($package === '') {
            return 'App';
        }

        $parts = array_map(static fn(string $part): string => ucfirst($part), explode('.', $package));

        return 'App\\' . implode('\\', $parts);
    }

    /**
     * @return list<array{method: string, path: string}>
     */
    private function expandHttpRuleBindings(HttpRule $httpRule): array
    {
        $bindings = [];

        $binding = $this->createRouteBinding($httpRule);
        if ($binding !== null) {
            $bindings[] = $binding;
        }

        /** @var iterable<HttpRule> $additionalBindings */
        $additionalBindings = $httpRule->getAdditionalBindings();
        foreach ($additionalBindings as $additionalBinding) {
            foreach ($this->expandHttpRuleBindings($additionalBinding) as $binding) {
                $bindings[] = $binding;
            }
        }

        return $bindings;
    }

    /**
     * @return array{method: string, path: string}|null
     */
    private function createRouteBinding(HttpRule $httpRule): ?array
    {
        foreach ([
            'get' => $httpRule->getGet(),
            'post' => $httpRule->getPost(),
            'put' => $httpRule->getPut(),
            'delete' => $httpRule->getDelete(),
            'patch' => $httpRule->getPatch(),
        ] as $method => $path) {
            if ($path !== '') {
                return [
                    'method' => strtoupper($method),
                    'path' => $path,
                ];
            }
        }

        $custom = $httpRule->getCustom();
        if ($custom !== null && $custom->getKind() !== '' && $custom->getPath() !== '') {
            return [
                'method' => strtoupper($custom->getKind()),
                'path' => $custom->getPath(),
            ];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function extractLengthDelimitedFields(string $bytes, int $fieldNumber): array
    {
        $offset = 0;
        $payloads = [];
        $length = \strlen($bytes);

        while ($offset < $length) {
            $tag = $this->readVarint($bytes, $offset);
            if ($tag === null) {
                break;
            }

            $currentFieldNumber = $tag >> 3;
            $wireType = $tag & 0x07;

            if ($wireType === 2) {
                $size = $this->readVarint($bytes, $offset);
                if ($size === null || $offset + $size > $length) {
                    break;
                }

                $payload = substr($bytes, $offset, $size);
                if ($currentFieldNumber === $fieldNumber) {
                    $payloads[] = $payload;
                }

                $offset += $size;

                continue;
            }

            if (!$this->skipWireValue($bytes, $offset, $wireType)) {
                break;
            }
        }

        return $payloads;
    }

    private function readVarint(string $bytes, int &$offset): ?int
    {
        $result = 0;
        $shift = 0;
        $length = \strlen($bytes);

        while ($offset < $length) {
            $byte = \ord($bytes[$offset]);
            ++$offset;

            $result |= (($byte & 0x7F) << $shift);
            if (($byte & 0x80) === 0) {
                return $result;
            }

            $shift += 7;
            if ($shift > 63) {
                return null;
            }
        }

        return null;
    }

    private function skipWireValue(string $bytes, int &$offset, int $wireType): bool
    {
        return match ($wireType) {
            0 => $this->skipVarint($bytes, $offset),
            1 => $this->skipFixedBytes($bytes, $offset, 8),
            5 => $this->skipFixedBytes($bytes, $offset, 4),
            default => false,
        };
    }

    private function skipVarint(string $bytes, int &$offset): bool
    {
        return $this->readVarint($bytes, $offset) !== null;
    }

    private function skipFixedBytes(string $bytes, int &$offset, int $size): bool
    {
        if ($offset + $size > \strlen($bytes)) {
            return false;
        }

        $offset += $size;

        return true;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\PhpGeneratorPlugin;
use ProtoPhpGen\Protoc\Binary\ProtobufReader;
use ProtoPhpGen\Protoc\Binary\ProtobufWriter;
use ProtoPhpGen\Protoc\PluginRequest;

final class BinaryPluginProtocolTest extends TestCase
{
    public function testPluginProcessesBinaryCodeGeneratorRequestAndProducesBinaryResponse(): void
    {
        $request = PluginRequest::fromStdin(
            $this->buildCodeGeneratorRequest(
                parameter: 'namespace=App\\Generated\\Transport,output_dir=gen,generate_endpoints=true,generate_operation_manifest=true',
                filesToGenerate: ['app/v1/health.proto'],
                protoFiles: [
                    $this->buildFileDescriptorProto(
                        name: 'app/v1/health.proto',
                        package: 'app.v1',
                        phpNamespace: 'App\\Api\\V1',
                        messages: [
                            $this->buildMessageDescriptorProto('HealthCheckRequest'),
                            $this->buildMessageDescriptorProto('HealthCheckResponse'),
                        ],
                        services: [
                            $this->buildServiceDescriptorProto(
                                'HealthService',
                                [
                                    $this->buildMethodDescriptorProto(
                                        'Check',
                                        '.app.v1.HealthCheckRequest',
                                        '.app.v1.HealthCheckResponse',
                                        'GET',
                                        '/api/v1/health',
                                    ),
                                ],
                            ),
                        ],
                    ),
                ],
            ),
        );

        $plugin = new PhpGeneratorPlugin();
        $response = $plugin->process($request);
        $decodedResponse = $this->decodeCodeGeneratorResponse($response->serialize());

        self::assertNull($decodedResponse['error']);
        self::assertCount(3, $decodedResponse['files']);
        self::assertSame(
            'gen/Generated/Transport/Api/V1/HealthService/CheckEndpoint.php',
            $decodedResponse['files'][0]['name'],
        );
        self::assertSame(
            'gen/Generated/Transport/Api/V1/HealthService/CheckHttpHandler.php',
            $decodedResponse['files'][1]['name'],
        );
        self::assertSame(
            'gen/Generated/OperationManifest/app/v1/health.php',
            $decodedResponse['files'][2]['name'],
        );
        self::assertStringContainsString(
            'interface CheckEndpoint',
            $decodedResponse['files'][0]['content'],
        );
        self::assertStringContainsString(
            'extends AbstractProtobufRpcHandler',
            $decodedResponse['files'][1]['content'],
        );
        self::assertStringContainsString(
            "'operation_id' => 'HealthService.Check'",
            $decodedResponse['files'][2]['content'],
        );
    }

    /**
     * @param list<string> $filesToGenerate
     * @param list<string> $protoFiles
     */
    private function buildCodeGeneratorRequest(string $parameter, array $filesToGenerate, array $protoFiles): string
    {
        $writer = new ProtobufWriter();

        foreach ($filesToGenerate as $fileToGenerate) {
            $writer->writeTag(1, 2);
            $writer->writeString($fileToGenerate);
        }

        $writer->writeTag(2, 2);
        $writer->writeString($parameter);

        foreach ($protoFiles as $protoFile) {
            $writer->writeTag(15, 2);
            $writer->writeMessage($protoFile);
        }

        return $writer->getData();
    }

    /**
     * @param list<string> $messages
     * @param list<string> $services
     */
    private function buildFileDescriptorProto(
        string $name,
        string $package,
        string $phpNamespace,
        array $messages,
        array $services,
    ): string {
        $writer = new ProtobufWriter();
        $writer->writeTag(1, 2);
        $writer->writeString($name);
        $writer->writeTag(2, 2);
        $writer->writeString($package);

        foreach ($messages as $message) {
            $writer->writeTag(4, 2);
            $writer->writeMessage($message);
        }

        foreach ($services as $service) {
            $writer->writeTag(6, 2);
            $writer->writeMessage($service);
        }

        $writer->writeTag(8, 2);
        $writer->writeMessage($this->buildFileOptions($phpNamespace));

        return $writer->getData();
    }

    private function buildFileOptions(string $phpNamespace): string
    {
        $writer = new ProtobufWriter();
        $writer->writeTag(41, 2);
        $writer->writeString($phpNamespace);

        return $writer->getData();
    }

    private function buildMessageDescriptorProto(string $name): string
    {
        $writer = new ProtobufWriter();
        $writer->writeTag(1, 2);
        $writer->writeString($name);

        return $writer->getData();
    }

    /**
     * @param list<string> $methods
     */
    private function buildServiceDescriptorProto(string $name, array $methods): string
    {
        $writer = new ProtobufWriter();
        $writer->writeTag(1, 2);
        $writer->writeString($name);

        foreach ($methods as $method) {
            $writer->writeTag(2, 2);
            $writer->writeMessage($method);
        }

        return $writer->getData();
    }

    private function buildMethodDescriptorProto(
        string $name,
        string $inputType,
        string $outputType,
        ?string $httpMethod = null,
        ?string $httpPath = null,
    ): string
    {
        $writer = new ProtobufWriter();
        $writer->writeTag(1, 2);
        $writer->writeString($name);
        $writer->writeTag(2, 2);
        $writer->writeString($inputType);
        $writer->writeTag(3, 2);
        $writer->writeString($outputType);

        if ($httpMethod !== null && $httpPath !== null) {
            $writer->writeTag(4, 2);
            $writer->writeMessage($this->buildMethodOptionsWithHttpRule($httpMethod, $httpPath));
        }

        return $writer->getData();
    }

    private function buildMethodOptionsWithHttpRule(string $httpMethod, string $httpPath): string
    {
        $writer = new ProtobufWriter();
        $writer->writeTag(72295728, 2);
        $writer->writeMessage($this->buildHttpRule($httpMethod, $httpPath));

        return $writer->getData();
    }

    private function buildHttpRule(string $httpMethod, string $httpPath): string
    {
        $fieldNumber = match (strtoupper($httpMethod)) {
            'GET' => 2,
            'PUT' => 3,
            'POST' => 4,
            'DELETE' => 5,
            'PATCH' => 6,
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$httpMethod}"),
        };

        $writer = new ProtobufWriter();
        $writer->writeTag($fieldNumber, 2);
        $writer->writeString($httpPath);

        return $writer->getData();
    }

    /**
     * @return array{
     *     error: string|null,
     *     files: list<array{name: string, content: string}>
     * }
     */
    private function decodeCodeGeneratorResponse(string $data): array
    {
        $reader = new ProtobufReader($data);
        $error = null;
        $files = [];

        while ($reader->hasMore()) {
            [$fieldNumber, $wireType] = $reader->readTag();

            if ($fieldNumber === 1 && $wireType === 2) {
                $error = $reader->readString();

                continue;
            }

            if ($fieldNumber === 15 && $wireType === 2) {
                $files[] = $this->decodeCodeGeneratorResponseFile($reader->readMessage());

                continue;
            }

            $reader->skipField($wireType);
        }

        return [
            'error' => $error,
            'files' => $files,
        ];
    }

    /**
     * @return array{name: string, content: string}
     */
    private function decodeCodeGeneratorResponseFile(string $data): array
    {
        $reader = new ProtobufReader($data);
        $name = '';
        $content = '';

        while ($reader->hasMore()) {
            [$fieldNumber, $wireType] = $reader->readTag();

            if ($fieldNumber === 1 && $wireType === 2) {
                $name = $reader->readString();

                continue;
            }

            if ($fieldNumber === 15 && $wireType === 2) {
                $content = $reader->readString();

                continue;
            }

            $reader->skipField($wireType);
        }

        return [
            'name' => $name,
            'content' => $content,
        ];
    }
}

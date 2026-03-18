<?php

declare(strict_types=1);

namespace App\Platform\Http\Handler;

use Google\Protobuf\Internal\Message;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract readonly class AbstractProtobufRpcHandler extends AbstractJsonHandler
{
    /**
     * @template T of Message
     * @param class-string<T> $messageClass
     * @return T|null
     */
    protected function decodeRequest(ServerRequestInterface $request, string $messageClass): ?Message
    {
        $message = new $messageClass();
        $body = (string) $request->getBody();

        if ($body === '') {
            return $message;
        }

        try {
            $message->mergeFromJsonString($body);

            return $message;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @throws \JsonException
     */
    protected function protobufResponse(Message $message, int $status = 200): ResponseInterface
    {
        return $this->jsonResponse($message->serializeToJsonString(), $status);
    }

    /**
     * @throws \JsonException
     */
    protected function invalidRequestResponse(string $message = 'Invalid request body'): ResponseInterface
    {
        return $this->jsonResponse(
            json_encode(['error' => $message], JSON_THROW_ON_ERROR),
            400,
        );
    }
}

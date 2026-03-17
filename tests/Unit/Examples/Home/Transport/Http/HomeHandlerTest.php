<?php

declare(strict_types=1);

namespace Tests\Unit\Examples\Home\Transport\Http;

use App\Examples\Home\Domain\HomeService;
use App\Examples\Home\Transport\Http\HomeHandler;
use App\Examples\Home\Transport\Mapping\HomeResponseMapper;
use App\Platform\DataMapping\DataTransferObjectMapper;
use App\Platform\Http\JsonResponse;
use App\Platform\Hydration\LimitedReflectionCache;
use App\Platform\Hydration\ReflectionHydrator;
use App\Platform\Hydration\SetterProtobufHydration;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class HomeHandlerTest extends TestCase
{
    private HomeHandler $handler;

    protected function setUp(): void
    {
        $homeService = new HomeService();

        $cache = new LimitedReflectionCache();
        $protobufHydration = new SetterProtobufHydration();
        $hydrator = new ReflectionHydrator($cache, $protobufHydration);

        $dtoMapper = new DataTransferObjectMapper($hydrator);
        $homeMapper = new HomeResponseMapper($dtoMapper);
        $this->handler = new HomeHandler($homeService, $homeMapper, new JsonResponse());
    }

    /**
     * @throws \JsonException
     */
    public function testHandleReturnsWelcomeMessage(): void
    {
        $request = new ServerRequest('GET', '/');
        $response = $this->handler->handle($request);

        self::assertEquals(200, $response->getStatusCode());
        self::assertEquals(
            'application/json',
            $response->getHeaderLine('Content-Type'),
        );

        $responseContent = (string) $response->getBody();
        $body = json_decode($responseContent, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($body);
        self::assertArrayHasKey('data', $body);
        self::assertIsArray($body['data']);
        self::assertArrayHasKey('message', $body['data']);
        self::assertEquals('Welcome to our API', $body['data']['message']);
    }
}

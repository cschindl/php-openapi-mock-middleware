<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Tests\Unit;

use Cschindl\OpenAPIMock\OpenApiMockMiddleware;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class OpenApiMockMiddlewareTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ServerRequestInterface&ObjectProphecy
     */
    private $request;

    /**
     * @var RequestHandlerInterface&ObjectProphecy
     */
    private $handler;

    /**
     * @var CacheItemPoolInterface&ObjectProphecy
     */
    private $cache;

    public function setUp(): void
    {
        parent::setUp();

        $this->request = $this->prophesize(ServerRequestInterface::class);
        $this->handler = $this->prophesize(RequestHandlerInterface::class);
        $this->cache = $this->prophesize(CacheItemPoolInterface::class);
    }

    /**
     * @return void
     */
    public function testMiddlewareCanBeCreated(): void
    {
        $middleware = new OpenApiMockMiddleware(
            new ResponseFactory(),
            new StreamFactory(),
            null,
            '',
            []
        );

        $response = $middleware->process($this->request->reveal(), $this->handler->reveal());

        self::assertInstanceOf(ResponseInterface::class, $response);
    }
}

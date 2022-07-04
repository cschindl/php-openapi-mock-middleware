<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Tests\Unit;

use Cschindl\OpenAPIMock\OpenApiMockMiddleware;
use InvalidArgumentException;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\Uri;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamException;
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
        $this->request->getUri()->willReturn(new Uri('https://localhost/api/v1'));
        $this->request->getMethod()->willReturn('GET');

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
            $this->createYamlFileWithContent(''),
            []
        );

        self::assertInstanceOf(OpenApiMockMiddleware::class, $middleware);
    }

    /**
     * @return void
     */
    public function testMiddlewareNoRessourceProvidedError(): void
    {
        $yaml = <<<YAML
openapi: 3.0.1
paths:
YAML;

        $middleware = new OpenApiMockMiddleware(
            new ResponseFactory(),
            new StreamFactory(),
            null,
            $this->createYamlFileWithContent($yaml),
            []
        );

        $response = $middleware->process($this->request->reveal(), $this->handler->reveal());

        self::assertInstanceOf(ResponseInterface::class, $response);
        self::assertEquals('', $response->getBody()->getContents());
    }

    /**
     * @param string $content
     * @return string
     * @throws vfsStreamException
     * @throws InvalidArgumentException
     */
    private function createYamlFileWithContent(string $content): string
    {
        $root = vfsStream::setup('root_dir');
        $file = vfsStream::newFile('spec.yaml');
        $file->setContent($content);
        $root->addChild($file);

        return $file->url();
    }
}

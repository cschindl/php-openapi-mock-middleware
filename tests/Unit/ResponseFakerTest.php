<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Tests\Unit;

use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use Cschindl\OpenAPIMock\ResponseFaker;
use League\OpenAPIValidation\PSR7\OperationAddress;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Vural\OpenAPIFaker\Exception\NoPath;
use Vural\OpenAPIFaker\Exception\NoResponse;
use Vural\OpenAPIFaker\Options;

class ResponseFakerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var OpenApi
     */
    private $schema;

    /**
     * @var ResponseFactoryInterface&ObjectProphecy
     */
    private $responseFactory;

    /**
     * @var ResponseFactoryInterface%ObjectProphecy
     */
    private $streamFactory;

    /**
     * @return void
     */
    public function setUp(): void
    {
        $yaml = <<<YAML
openapi: 3.0.1
paths:
  /pet:
    get:
      responses:
        '200':
          content:
            application/json:
              schema:
                Pet:
                  type: object
                  properties:
                    id:
                      type: integer
YAML;
        $this->schema = Reader::readFromYaml($yaml);

        $stream = $this->prophesize(StreamInterface::class);
        $response = $this->prophesize(ResponseInterface::class);
        $response->withBody($stream)->willReturn($response);
        $response->withAddedHeader('Content-Type', 'application/json')->willReturn($response);
        $response->withStatus(200)->willReturn($response);

        $this->responseFactory = $this->prophesize(ResponseFactoryInterface::class);
        $this->responseFactory->createResponse()->willReturn($response->reveal());
        $this->streamFactory = $this->prophesize(StreamFactoryInterface::class);
        $this->streamFactory->createStream(Argument::any())->willReturn($stream->reveal());
    }

    /**
     * @return void
     */
    public function testMockPossibleResponseWithExistingStatusCode(): void
    {
        $operationAddress = new OperationAddress('/pet', 'GET');

        $responseFaker = new ResponseFaker(
            $this->responseFactory->reveal(),
            $this->streamFactory->reveal(),
            ['strategy' => Options::STRATEGY_STATIC]
        );

        $response = $responseFaker->mockPossibleResponse($this->schema, $operationAddress, '200', 'application/json');

        self::assertInstanceOf(ResponseInterface::class, $response);
    }

    /**
     * @return void
     */
    public function testMockPossibleResponseWithMultipleStatusCodes(): void
    {
        $operationAddress = new OperationAddress('/pet', 'GET');

        $responseFaker = new ResponseFaker(
            $this->responseFactory->reveal(),
            $this->streamFactory->reveal(),
            ['strategy' => Options::STRATEGY_STATIC]
        );

        $response = $responseFaker->mockPossibleResponse($this->schema, $operationAddress, ['201', '200'], 'application/json');

        self::assertInstanceOf(ResponseInterface::class, $response);
    }

    /**
     * @return void
     */
    public function testMockPossibleResponseWithNoResponse(): void
    {
        $operationAddress = new OperationAddress('/pet', 'GET');

        $responseFaker = new ResponseFaker(
            $this->responseFactory->reveal(),
            $this->streamFactory->reveal(),
            ['strategy' => Options::STRATEGY_STATIC]
        );

        $this->expectException(NoResponse::class);

        $responseFaker->mockPossibleResponse($this->schema, $operationAddress, '400', 'application/json');
    }

    /**
     * @return void
     */
    public function testMockPossibleResponseWithNoPath(): void
    {
        $operationAddress = new OperationAddress('/', 'GET');

        $responseFaker = new ResponseFaker(
            $this->responseFactory->reveal(),
            $this->streamFactory->reveal(),
            ['strategy' => Options::STRATEGY_STATIC]
        );

        $this->expectException(NoPath::class);

        $responseFaker->mockPossibleResponse($this->schema, $operationAddress, '200', 'application/json');
    }
}

<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Tests\Unit\Response;

use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use Cschindl\OpenAPIMock\Exception\ValidationException;
use Cschindl\OpenAPIMock\Response\ResponseFaker;
use Exception;
use League\OpenAPIValidation\PSR7\OperationAddress;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Vural\OpenAPIFaker\Exception\NoExample;
use Vural\OpenAPIFaker\Exception\NoPath;
use Vural\OpenAPIFaker\Exception\NoResponse;
use Vural\OpenAPIFaker\Options;

class ResponseFakerTest extends TestCase
{
    use ProphecyTrait;

    private OpenApi $schema;

    private ResponseFactoryInterface|ObjectProphecy $responseFactory;

    private StreamFactoryInterface|ObjectProphecy $streamFactory;

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
              examples: 
                testExample:
                  value:
                    id: 100
YAML;
        $this->schema = Reader::readFromYaml($yaml);

        $stream = $this->prophesize(StreamInterface::class);
        $response = $this->prophesize(ResponseInterface::class);
        $response->withBody($stream)->willReturn($response);
        $response->withAddedHeader('Content-Type', 'application/json')->willReturn($response);
        $response->withStatus(200)->willReturn($response);

        $this->responseFactory = $this->prophesize(ResponseFactoryInterface::class);
        $this->responseFactory->createResponse()->willReturn($response);
        $this->streamFactory = $this->prophesize(StreamFactoryInterface::class);
        $this->streamFactory->createStream(Argument::any())->willReturn($stream);
    }

    public function testMockWithStatusCode(): void
    {
        $operationAddress = new OperationAddress('/pet', 'GET');

        $responseFaker = new ResponseFaker(
            $this->responseFactory->reveal(),
            $this->streamFactory->reveal(),
            ['strategy' => Options::STRATEGY_STATIC]
        );

        $response = $responseFaker->mock($this->schema, $operationAddress, '200', 'application/json');

        self::assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testMockWithMultipleStatusCodes(): void
    {
        $operationAddress = new OperationAddress('/pet', 'GET');

        $responseFaker = new ResponseFaker(
            $this->responseFactory->reveal(),
            $this->streamFactory->reveal(),
            ['strategy' => Options::STRATEGY_STATIC]
        );

        $response = $responseFaker->mock($this->schema, $operationAddress, ['201', '200'], 'application/json');

        self::assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testMockWithNoResponse(): void
    {
        $operationAddress = new OperationAddress('/pet', 'GET');

        $responseFaker = new ResponseFaker(
            $this->responseFactory->reveal(),
            $this->streamFactory->reveal(),
            ['strategy' => Options::STRATEGY_STATIC]
        );

        $this->expectException(NoResponse::class);

        $responseFaker->mock($this->schema, $operationAddress, '400', 'application/json');
    }

    public function testMockWithNoPath(): void
    {
        $operationAddress = new OperationAddress('/', 'GET');

        $responseFaker = new ResponseFaker(
            $this->responseFactory->reveal(),
            $this->streamFactory->reveal(),
            ['strategy' => Options::STRATEGY_STATIC]
        );

        $this->expectException(NoPath::class);

        $responseFaker->mock($this->schema, $operationAddress, '200', 'application/json');
    }

    public function testMockWithNoExample(): void
    {
        $operationAddress = new OperationAddress('/pet', 'GET');

        $responseFaker = new ResponseFaker(
            $this->responseFactory->reveal(),
            $this->streamFactory->reveal(),
            ['strategy' => Options::STRATEGY_STATIC]
        );

        $this->expectException(NoExample::class);

        $responseFaker->mock($this->schema, $operationAddress, '200', 'application/json', 'wrongExample');
    }

    public function testHandleExceptionWithRequestException(): void
    {
        $stream = $this->prophesize(StreamInterface::class);
        $response = $this->prophesize(ResponseInterface::class);
        $response->withBody($stream)->willReturn($response);
        $response->withAddedHeader('Content-Type', 'application/json')->willReturn($response);
        $response->withStatus(404)->willReturn($response);
        $this->responseFactory->createResponse()->willReturn($response);

        $body = [
            'type' => 'NOT_FOUND',
            'title' => 'The server cannot find the requested content',
            'detail' => '',
            'status' => 404,
        ];
        $this->streamFactory->createStream(json_encode($body))->willReturn($stream);

        $responseFaker = new ResponseFaker(
            $this->responseFactory->reveal(),
            $this->streamFactory->reveal(),
            ['strategy' => Options::STRATEGY_STATIC]
        );

        $response = $responseFaker->handleException(ValidationException::forNotFound(), 'application/json');

        self::assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testHandleExceptionWithUnexpectedException(): void
    {
        $stream = $this->prophesize(StreamInterface::class);
        $response = $this->prophesize(ResponseInterface::class);
        $response->withBody($stream)->willReturn($response);
        $response->withAddedHeader('Content-Type', 'application/json')->willReturn($response);
        $response->withStatus(500)->willReturn($response);
        $this->responseFactory->createResponse()->willReturn($response);

        $body = [
            'type' => 'ERROR',
            'title' => 'Unexpected error occurred',
            'detail' => 'Unexpected Error',
            'status' => 500,
        ];
        $this->streamFactory->createStream(json_encode($body))->willReturn($stream);

        $responseFaker = new ResponseFaker(
            $this->responseFactory->reveal(),
            $this->streamFactory->reveal(),
            ['strategy' => Options::STRATEGY_STATIC]
        );

        $response = $responseFaker->handleException(new Exception('Unexpected Error'), 'application/json');

        self::assertInstanceOf(ResponseInterface::class, $response);
    }
}

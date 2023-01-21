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
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Vural\OpenAPIFaker\Exception\NoExample;
use Vural\OpenAPIFaker\Exception\NoPath;
use Vural\OpenAPIFaker\Exception\NoResponse;
use Vural\OpenAPIFaker\Options;

use function json_encode;

class ResponseFakerTest extends TestCase
{
    use ProphecyTrait;

    private OpenApi $schema;

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
    }

    public function testMockWithStatusCode(): void
    {
        $operationAddress = new OperationAddress('/pet', 'GET');

        $responseFaker = $this->createResponseFaker();

        $result = $responseFaker->mock($this->schema, $operationAddress, '200', 'application/json');

        self::assertInstanceOf(ResponseInterface::class, $result);
    }

    public function testMockWithMultipleStatusCodes(): void
    {
        $operationAddress = new OperationAddress('/pet', 'GET');

        $responseFaker = $this->createResponseFaker();

        $result = $responseFaker->mock($this->schema, $operationAddress, ['201', '200'], 'application/json');

        self::assertInstanceOf(ResponseInterface::class, $result);
    }

    public function testMockWithNoResponse(): void
    {
        $operationAddress = new OperationAddress('/pet', 'GET');

        $responseFaker = $this->createResponseFaker();

        $this->expectException(NoResponse::class);

        $responseFaker->mock($this->schema, $operationAddress, '400', 'application/json');
    }

    public function testMockWithNoPath(): void
    {
        $operationAddress = new OperationAddress('/', 'GET');

        $responseFaker = $this->createResponseFaker();

        $this->expectException(NoPath::class);

        $responseFaker->mock($this->schema, $operationAddress, '200', 'application/json');
    }

    public function testMockWithNoExample(): void
    {
        $operationAddress = new OperationAddress('/pet', 'GET');

        $responseFaker = $this->createResponseFaker();

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

        $responseFactory = $this->prophesize(ResponseFactoryInterface::class);
        $responseFactory->createResponse()->willReturn($response);

        $body = [
            'type' => 'NOT_FOUND',
            'title' => 'The server cannot find the requested content',
            'detail' => '',
            'status' => 404,
        ];
        $streamFactory = $this->prophesize(StreamFactoryInterface::class);
        $streamFactory->createStream(json_encode($body))->willReturn($stream);

        $responseFaker = new ResponseFaker(
            $responseFactory->reveal(),
            $streamFactory->reveal(),
            (new Options())->setStrategy(Options::STRATEGY_STATIC)
        );

        $result = $responseFaker->handleException(ValidationException::forNotFound(), 'application/json');

        self::assertInstanceOf(ResponseInterface::class, $result);
    }

    public function ttestHandleExceptionWithUnexpectedException(): void
    {
        $stream = $this->prophesize(StreamInterface::class);
        $response = $this->prophesize(ResponseInterface::class);
        $response->withBody($stream)->willReturn($response);
        $response->withAddedHeader('Content-Type', 'application/json')->willReturn($response);
        $response->withStatus(500)->willReturn($response);

        $responseFactory = $this->prophesize(ResponseFactoryInterface::class);
        $responseFactory->createResponse()->willReturn($response);

        $body = [
            'type' => 'ERROR',
            'title' => 'Unexpected error occurred',
            'detail' => 'Unexpected Error',
            'status' => 500,
        ];
        $streamFactory = $this->prophesize(StreamFactoryInterface::class);
        $streamFactory->createStream(json_encode($body))->willReturn($stream);

        $responseFaker = new ResponseFaker(
            $responseFactory->reveal(),
            $streamFactory->reveal(),
            (new Options())->setStrategy(Options::STRATEGY_STATIC)
        );

        $result = $responseFaker->handleException(new Exception('Unexpected Error'), 'application/json');

        self::assertInstanceOf(ResponseInterface::class, $result);
    }

    private function createResponseFaker(): ResponseFaker
    {
        $stream = $this->prophesize(StreamInterface::class);
        $response = $this->prophesize(ResponseInterface::class);
        $response->withBody($stream)->willReturn($response);
        $response->withAddedHeader('Content-Type', 'application/json')->willReturn($response);
        $response->withStatus(200)->willReturn($response);

        $responseFactory = $this->prophesize(ResponseFactoryInterface::class);
        $responseFactory->createResponse()->willReturn($response);
        $streamFactory = $this->prophesize(StreamFactoryInterface::class);
        $streamFactory->createStream(Argument::any())->willReturn($stream);

        $options = (new Options())->setStrategy(Options::STRATEGY_STATIC);

        return new ResponseFaker(
            $responseFactory->reveal(),
            $streamFactory->reveal(),
            $options
        );
    }
}

<?php

declare(strict_types=1);

namespace Cschindl\OpenApiMockMiddleware\Tests\Unit;

use cebe\openapi\spec\OpenApi;
use Cschindl\OpenApiMockMiddleware\OpenApiMockMiddleware;
use Cschindl\OpenApiMockMiddleware\OpenApiMockMiddlewareConfig;
use Cschindl\OpenApiMockMiddleware\Request\RequestHandler;
use Cschindl\OpenApiMockMiddleware\Response\ResponseHandler;
use Cschindl\OpenApiMockMiddleware\Validator\RequestValidator;
use Cschindl\OpenApiMockMiddleware\Validator\RequestValidatorResult;
use Cschindl\OpenApiMockMiddleware\Validator\ResponseValidator;
use Cschindl\OpenApiMockMiddleware\Validator\ResponseValidatorResult;
use League\OpenAPIValidation\PSR7\Exception\NoOperation;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\OperationAddress;
use Nyholm\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class OpenApiMockMiddlewareTest extends TestCase
{
    use ProphecyTrait;

    public function testHandleInActive(): void
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn(new Uri('http://localhost:4010/test'));
        $request->getMethod()->willReturn('GET');
        $request->getHeader(Argument::any())->willReturn([]);
        $request->getHeader(OpenApiMockMiddleware::HEADER_OPENAPI_MOCK_ACTIVE)->willReturn([]);

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle($request)->willReturn($response);

        $requestValidator = $this->prophesize(RequestValidator::class);

        $requestHandler = $this->prophesize(RequestHandler::class);

        $responseValidator = $this->prophesize(ResponseValidator::class);

        $responseHandler = $this->prophesize(ResponseHandler::class);

        $middleware = new OpenApiMockMiddleware(
            $requestHandler->reveal(),
            $requestValidator->reveal(),
            $responseHandler->reveal(),
            $responseValidator->reveal(),
            new OpenApiMockMiddlewareConfig(true, true)
        );

        $result = $middleware->process($request->reveal(), $handler->reveal());

        self::assertInstanceOf(ResponseInterface::class, $result);
    }

    public function testHandleValidRequest(): void
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn(new Uri('http://localhost:4010/test'));
        $request->getMethod()->willReturn('GET');
        $request->getHeader(Argument::any())->willReturn([]);
        $request->getHeader(OpenApiMockMiddleware::HEADER_OPENAPI_MOCK_ACTIVE)->willReturn(['true']);
        $request->getHeader(OpenApiMockMiddleware::HEADER_OPENAPI_MOCK_STATUSCODE)->willReturn(['400']);
        $request->getHeader(OpenApiMockMiddleware::HEADER_CONTENT_TYPE)->willReturn(['application/problem+json']);
        $request->getHeader(OpenApiMockMiddleware::HEADER_OPENAPI_MOCK_EXAMPLE)->willReturn(['testExample']);
        $handler = $this->prophesize(RequestHandlerInterface::class);

        $schema = new OpenApi([]);
        $operationAddress = new OperationAddress('/test', 'GET');
        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $requestValidator = $this->prophesize(RequestValidator::class);
        $requestValidator->parse($request, true)->willReturn(new RequestValidatorResult($schema, $operationAddress));

        $requestHandler = $this->prophesize(RequestHandler::class);
        $requestHandler->handleValidRequest(
            $schema,
            $operationAddress,
            'application/problem+json',
            '400',
            'testExample'
        )->willReturn($response);

        $responseValidator = $this->prophesize(ResponseValidator::class);
        $responseValidator->parse(
            $response,
            $operationAddress,
            true
        )->willReturn(new ResponseValidatorResult());

        $responseHandler = $this->prophesize(ResponseHandler::class);

        $middleware = new OpenApiMockMiddleware(
            $requestHandler->reveal(),
            $requestValidator->reveal(),
            $responseHandler->reveal(),
            $responseValidator->reveal(),
            new OpenApiMockMiddlewareConfig(true, true)
        );

        $result = $middleware->process($request->reveal(), $handler->reveal());

        self::assertInstanceOf(ResponseInterface::class, $result);
    }

    public function testHandleInValidRequest(): void
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn(new Uri('http://localhost:4010/dummy-path'));
        $request->getMethod()->willReturn('GET');
        $request->getHeader(Argument::any())->willReturn([]);
        $request->getHeader(OpenApiMockMiddleware::HEADER_OPENAPI_MOCK_ACTIVE)->willReturn(['true']);
        $handler = $this->prophesize(RequestHandlerInterface::class);

        $schema = new OpenApi([]);
        $operationAddress = new OperationAddress('/test', 'GET');
        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $exception = NoOperation::fromPath('/dummy-path');
        $requestValidator = $this->prophesize(RequestValidator::class);
        $requestValidator->parse($request, true)->willReturn(new RequestValidatorResult($schema, $operationAddress, $exception));

        $requestHandler = $this->prophesize(RequestHandler::class);
        $requestHandler->handleInvalidRequest(
            $exception,
            $schema,
            $operationAddress,
            'application/json',
        )->willReturn($response);

        $responseValidator = $this->prophesize(ResponseValidator::class);

        $responseHandler = $this->prophesize(ResponseHandler::class);

        $middleware = new OpenApiMockMiddleware(
            $requestHandler->reveal(),
            $requestValidator->reveal(),
            $responseHandler->reveal(),
            $responseValidator->reveal(),
            new OpenApiMockMiddlewareConfig(true, true)
        );

        $result = $middleware->process($request->reveal(), $handler->reveal());

        self::assertInstanceOf(ResponseInterface::class, $result);
    }

    public function testHandleInValidResponse(): void
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn(new Uri('http://localhost:4010/test'));
        $request->getMethod()->willReturn('GET');
        $request->getHeader(Argument::any())->willReturn([]);
        $request->getHeader(OpenApiMockMiddleware::HEADER_OPENAPI_MOCK_ACTIVE)->willReturn(['true']);
        $handler = $this->prophesize(RequestHandlerInterface::class);

        $schema = new OpenApi([]);
        $operationAddress = new OperationAddress('/test', 'GET');
        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $requestValidator = $this->prophesize(RequestValidator::class);
        $requestValidator->parse($request, true)->willReturn(new RequestValidatorResult($schema, $operationAddress));

        $requestHandler = $this->prophesize(RequestHandler::class);
        $requestHandler->handleValidRequest(
            $schema,
            $operationAddress,
            'application/json',
            null,
            null
        )->willReturn($response);

        $exception = new ValidationFailed('Invalid response');
        $responseValidator = $this->prophesize(ResponseValidator::class);
        $responseValidator->parse(
            $response,
            $operationAddress,
            true
        )->willReturn(new ResponseValidatorResult($exception));

        $responseHandler = $this->prophesize(ResponseHandler::class);
        $responseHandler->handleInvalidResponse($exception, 'application/json');

        $middleware = new OpenApiMockMiddleware(
            $requestHandler->reveal(),
            $requestValidator->reveal(),
            $responseHandler->reveal(),
            $responseValidator->reveal(),
            new OpenApiMockMiddlewareConfig(true, true)
        );

        $result = $middleware->process($request->reveal(), $handler->reveal());

        self::assertInstanceOf(ResponseInterface::class, $result);
    }
}

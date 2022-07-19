<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Tests\Unit;

use cebe\openapi\spec\OpenApi;
use Cschindl\OpenAPIMock\ErrorResponseGenerator;
use Cschindl\OpenAPIMock\Exception\ValidationException;
use Cschindl\OpenAPIMock\OpenApiMockMiddleware;
use Cschindl\OpenAPIMock\RequestValidator;
use Cschindl\OpenAPIMock\ResponseFaker;
use Cschindl\OpenAPIMock\ResponseValidator;
use League\OpenAPIValidation\PSR7\Exception\NoPath;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\OperationAddress;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

class OpenApiMockMiddlewareTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @return void
     */
    public function testHandleValidRequest(): void
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $handler = $this->prophesize(RequestHandlerInterface::class);

        $schema = $this->prophesize(OpenApi::class);
        $operationAddress = new OperationAddress('/', 'GET');

        $requestValidator = $this->prophesize(RequestValidator::class);
        $requestValidator->validateRequest($request)->willReturn($operationAddress);
        $requestValidator->getSchema()->willReturn($schema);

        $responseValidator = $this->prophesize(ResponseValidator::class);

        $responseFaker = $this->prophesize(ResponseFaker::class);
        $responseFaker->mockPossibleResponse($schema, $operationAddress, ['200', '201'], 'application/json')->willReturn(
            $this->prophesize(ResponseInterface::class)->reveal()
        );

        $errorResponseGenerator = $this->prophesize(ErrorResponseGenerator::class);

        $middleware = new OpenApiMockMiddleware(
            $requestValidator->reveal(),
            $responseValidator->reveal(),
            $responseFaker->reveal(),
            $errorResponseGenerator->reveal()
        );

        $response = $middleware->process($request->reveal(), $handler->reveal());

        self::assertInstanceOf(ResponseInterface::class, $response);
    }

    /**
     * @return void
     */
    public function testHandleInValidRequest(): void
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $uri = $this->prophesize(UriInterface::class);
        $uri->getPath()->willReturn('/');
        $request->getUri()->willReturn($uri);
        $request->getMethod()->willReturn('GET');
        $handler = $this->prophesize(RequestHandlerInterface::class);

        $schema = $this->prophesize(OpenApi::class);
        $operationAddress = new OperationAddress('/', 'GET');

        $requestValidator = $this->prophesize(RequestValidator::class);
        $requestValidator->validateRequest($request)->willThrow(NoPath::fromPath('/'));
        $requestValidator->getSchema()->willReturn($schema);

        $responseValidator = $this->prophesize(ResponseValidator::class);

        $responseFaker = $this->prophesize(ResponseFaker::class);
        $responseFaker->mockPossibleResponse($schema, $operationAddress, ['404', '400', '500', 'default'], 'application/json')->willReturn(
            $this->prophesize(ResponseInterface::class)->reveal()
        );

        $errorResponseGenerator = $this->prophesize(ErrorResponseGenerator::class);

        $middleware = new OpenApiMockMiddleware(
            $requestValidator->reveal(),
            $responseValidator->reveal(),
            $responseFaker->reveal(),
            $errorResponseGenerator->reveal()
        );

        $response = $middleware->process($request->reveal(), $handler->reveal());

        self::assertInstanceOf(ResponseInterface::class, $response);
    }

    /**
     * @return void
     */
    public function testHandleInValidResponse(): void
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $uri = $this->prophesize(UriInterface::class);
        $uri->getPath()->willReturn('/');
        $request->getUri()->willReturn($uri);
        $request->getMethod()->willReturn('GET');
        $handler = $this->prophesize(RequestHandlerInterface::class);

        $schema = $this->prophesize(OpenApi::class);
        $operationAddress = new OperationAddress('/', 'GET');

        $requestValidator = $this->prophesize(RequestValidator::class);
        $requestValidator->validateRequest($request)->willReturn($operationAddress);
        $requestValidator->getSchema()->willReturn($schema);

        $responseValidator = $this->prophesize(ResponseValidator::class);
        $responseValidator->validateResonse(
            Argument::type(OperationAddress::class),
            Argument::type(ResponseInterface::class)
        )->willThrow(new ValidationFailed());

        $responseFaker = $this->prophesize(ResponseFaker::class);
        $responseFaker->mockPossibleResponse($schema, $operationAddress, ['200', '201'], 'application/json')->willReturn(
            $this->prophesize(ResponseInterface::class)->reveal()
        );

        $response = $this->prophesize(ResponseInterface::class)->reveal();
        $errorResponseGenerator = $this->prophesize(ErrorResponseGenerator::class);
        $errorResponseGenerator->handleException(Argument::type(ValidationException::class), 'application/json')->willReturn(
            $response
        );

        $middleware = new OpenApiMockMiddleware(
            $requestValidator->reveal(),
            $responseValidator->reveal(),
            $responseFaker->reveal(),
            $errorResponseGenerator->reveal()
        );

        $response = $middleware->process($request->reveal(), $handler->reveal());

        self::assertInstanceOf(ResponseInterface::class, $response);
    }
}

<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Tests\Unit;

use cebe\openapi\spec\OpenApi;
use Cschindl\OpenAPIMock\ErrorResponseGenerator;
use Cschindl\OpenAPIMock\Exception\ValidationException;
use Cschindl\OpenAPIMock\OpenApiMockMiddleware;
use Cschindl\OpenAPIMock\ResponseFaker;
use League\OpenAPIValidation\PSR7\Exception\NoPath;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ResponseValidator;
use League\OpenAPIValidation\PSR7\ServerRequestValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
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

    public function testHandleValidRequest(): void
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn(new Uri('http://localhost:4010/test'));
        $request->getMethod()->willReturn('GET');
        $request->getHeader(Argument::any())->willReturn([]);
        $handler = $this->prophesize(RequestHandlerInterface::class);

        $schema = new OpenApi([
            'openapi' => '3.0.2',
            'paths' => [
                '/test' => ['description' => 'something'],
            ],
        ]);
        $operationAddress = new OperationAddress('/test', 'GET');

        $requestValidator = $this->prophesize(ServerRequestValidator::class);
        $requestValidator->validate($request)->willReturn($operationAddress);
        $requestValidator->getSchema()->willReturn($schema);

        $responseValidator = $this->prophesize(ResponseValidator::class);

        $validatorBuilder = $this->prophesize(ValidatorBuilder::class);
        $validatorBuilder->getServerRequestValidator()->willReturn($requestValidator);
        $validatorBuilder->getResponseValidator()->willReturn($responseValidator);

        $responseFaker = $this->prophesize(ResponseFaker::class);
        $responseFaker->mockPossibleResponse($schema, $operationAddress, ['200', '201'], 'application/json', null)->willReturn(
            $this->prophesize(ResponseInterface::class)->reveal()
        );

        $errorResponseGenerator = $this->prophesize(ErrorResponseGenerator::class);

        $middleware = new OpenApiMockMiddleware(
            $validatorBuilder->reveal(),
            $responseFaker->reveal(),
            $errorResponseGenerator->reveal()
        );

        $response = $middleware->process($request->reveal(), $handler->reveal());

        self::assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testHandleInValidRequest(): void
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn(new Uri('http://localhost:4010/pet'));
        $request->getMethod()->willReturn('GET');
        $request->getHeader(Argument::any())->willReturn([]);
        $handler = $this->prophesize(RequestHandlerInterface::class);

        $schema = new OpenApi([
            'openapi' => '3.0.2',
            'paths' => [],
        ]);
        $operationAddress = new OperationAddress('/pet', 'GET');

        $requestValidator = $this->prophesize(ServerRequestValidator::class);
        $requestValidator->validate($request)->willThrow(NoPath::fromPath('/'));
        $requestValidator->getSchema()->willReturn($schema);

        $responseValidator = $this->prophesize(ResponseValidator::class);

        $validatorBuilder = $this->prophesize(ValidatorBuilder::class);
        $validatorBuilder->getServerRequestValidator()->willReturn($requestValidator);
        $validatorBuilder->getResponseValidator()->willReturn($responseValidator);

        $responseFaker = $this->prophesize(ResponseFaker::class);
        $responseFaker->mockPossibleResponse($schema, $operationAddress, ['404', '400', '500', 'default'], 'application/json')->willReturn(
            $this->prophesize(ResponseInterface::class)->reveal()
        );

        $errorResponseGenerator = $this->prophesize(ErrorResponseGenerator::class);

        $middleware = new OpenApiMockMiddleware(
            $validatorBuilder->reveal(),
            $responseFaker->reveal(),
            $errorResponseGenerator->reveal()
        );

        $response = $middleware->process($request->reveal(), $handler->reveal());

        self::assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testMissingPathInSchema(): void
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn(new Uri('http://localhost:4010/test'));
        $request->getMethod()->willReturn('GET');
        $request->getHeader(Argument::any())->willReturn([]);
        $handler = $this->prophesize(RequestHandlerInterface::class);

        $schema = new OpenApi(['openapi' => '3.0.2']);
        $operationAddress = new OperationAddress('/test', 'GET');

        $requestValidator = $this->prophesize(ServerRequestValidator::class);
        $requestValidator->validate($request)->willReturn($operationAddress);
        $requestValidator->getSchema()->willReturn($schema);

        $responseValidator = $this->prophesize(ResponseValidator::class);

        $validatorBuilder = $this->prophesize(ValidatorBuilder::class);
        $validatorBuilder->getServerRequestValidator()->willReturn($requestValidator);
        $validatorBuilder->getResponseValidator()->willReturn($responseValidator);

        $responseFaker = $this->prophesize(ResponseFaker::class);
        $errorResponseGenerator = $this->prophesize(ErrorResponseGenerator::class);
        $errorResponseGenerator->handleException(Argument::type(ValidationException::class), 'application/json')->willReturn(
            $this->prophesize(ResponseInterface::class)->reveal()
        );

        $middleware = new OpenApiMockMiddleware(
            $validatorBuilder->reveal(),
            $responseFaker->reveal(),
            $errorResponseGenerator->reveal()
        );

        $response = $middleware->process($request->reveal(), $handler->reveal());

        self::assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testHandleInValidResponse(): void
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn(new Uri('http://localhost:4010/pet'));
        $request->getMethod()->willReturn('GET');
        $request->getHeader(Argument::any())->willReturn([]);
        $handler = $this->prophesize(RequestHandlerInterface::class);

        $schema = new OpenApi([
            'openapi' => '3.0.2',
            'paths' => [],
        ]);
        $operationAddress = new OperationAddress('/', 'GET');

        $requestValidator = $this->prophesize(ServerRequestValidator::class);
        $requestValidator->validate($request)->willReturn($operationAddress);
        $requestValidator->getSchema()->willReturn($schema);

        $responseValidator = $this->prophesize(ResponseValidator::class);
        $responseValidator->validate(
            Argument::type(OperationAddress::class),
            Argument::type(ResponseInterface::class)
        )->willThrow(new ValidationFailed());

        $validatorBuilder = $this->prophesize(ValidatorBuilder::class);
        $validatorBuilder->getServerRequestValidator()->willReturn($requestValidator);
        $validatorBuilder->getResponseValidator()->willReturn($responseValidator);

        $responseFaker = $this->prophesize(ResponseFaker::class);
        $responseFaker->mockPossibleResponse($schema, $operationAddress, ['200', '201'], 'application/json', null)->willReturn(
            $this->prophesize(ResponseInterface::class)->reveal()
        );

        $response = $this->prophesize(ResponseInterface::class)->reveal();
        $errorResponseGenerator = $this->prophesize(ErrorResponseGenerator::class);
        $errorResponseGenerator->handleException(Argument::type(ValidationException::class), 'application/json')->willReturn(
            $response
        );

        $middleware = new OpenApiMockMiddleware(
            $validatorBuilder->reveal(),
            $responseFaker->reveal(),
            $errorResponseGenerator->reveal()
        );

        $response = $middleware->process($request->reveal(), $handler->reveal());

        self::assertInstanceOf(ResponseInterface::class, $response);
    }
}

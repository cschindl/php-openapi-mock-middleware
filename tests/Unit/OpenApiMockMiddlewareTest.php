<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Tests\Unit;

use cebe\openapi\spec\OpenApi;
use Cschindl\OpenAPIMock\ErrorResponseGenerator;
use Cschindl\OpenAPIMock\OpenApiMockMiddleware;
use Cschindl\OpenAPIMock\RequestValidator;
use Cschindl\OpenAPIMock\ResponseFaker;
use Cschindl\OpenAPIMock\ResponseValidator;
use League\OpenAPIValidation\PSR7\OperationAddress;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class OpenApiMockMiddlewareTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @return void
     */
    public function testCanProcess(): void
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
        $responseFaker->mockResponse($schema, $operationAddress, 200, 'application/json')->willReturn(
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
}

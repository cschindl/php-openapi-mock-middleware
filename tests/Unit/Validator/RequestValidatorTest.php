<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Tests\Unit\Validator;

use cebe\openapi\spec\OpenApi;
use Cschindl\OpenAPIMock\Exception\RoutingException;
use Cschindl\OpenAPIMock\Validator\RequestValidator;
use Cschindl\OpenAPIMock\Validator\RequestValidatorResult;
use Exception;
use League\OpenAPIValidation\PSR7\Exception\NoPath;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ServerRequestValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Nyholm\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ServerRequestInterface;

class RequestValidatorTest extends TestCase
{
    use ProphecyTrait;

    public function testParseWithValidRequestWithoutValidate(): void
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn(new Uri('http://localhost:4010/test'));
        $request->getMethod()->willReturn('GET');

        $schema = new OpenApi([
            'openapi' => '3.0.2',
            'paths' => [
                '/test' => ['description' => 'something'],
            ],
        ]);
        $operationAddress = new OperationAddress('/test', 'GET');

        $requestValidator  = $this->prophesize(ServerRequestValidator::class);
        $requestValidator->validate($request)->shouldNotBeCalled();
        $requestValidator->getSchema()->willReturn($schema);

        $validatorBuilder = $this->prophesize(ValidatorBuilder::class);
        $validatorBuilder->getServerRequestValidator()->willReturn($requestValidator);

        $responseHandler = new RequestValidator($validatorBuilder->reveal());

        $result = $responseHandler->parse($request->reveal(), false);

        self::assertEquals(new RequestValidatorResult($schema, $operationAddress), $result);
    }

    public function testParseWithValidRequestWithValidate(): void
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn(new Uri('http://localhost:4010/test'));
        $request->getMethod()->willReturn('GET');

        $schema = new OpenApi([
            'openapi' => '3.0.2',
            'paths' => [
                '/test' => ['description' => 'something'],
            ],
        ]);
        $operationAddress = new OperationAddress('/test', 'GET');

        $requestValidator  = $this->prophesize(ServerRequestValidator::class);
        $requestValidator->validate($request)->willReturn($operationAddress)->shouldBeCalled();
        $requestValidator->getSchema()->willReturn($schema);

        $validatorBuilder = $this->prophesize(ValidatorBuilder::class);
        $validatorBuilder->getServerRequestValidator()->willReturn($requestValidator);

        $responseHandler = new RequestValidator($validatorBuilder->reveal());

        $result = $responseHandler->parse($request->reveal(), true);

        self::assertEquals(new RequestValidatorResult($schema, $operationAddress), $result);
    }

    public function testParseWithInValidRequest(): void
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn(new Uri('http://localhost:4010/test'));
        $request->getMethod()->willReturn('GET');

        $schema = new OpenApi([
            'openapi' => '3.0.2',
            'paths' => [
                '/test' => ['description' => 'something'],
            ],
        ]);
        $operationAddress = new OperationAddress('/test', 'GET');

        $exception = new Exception('Invalid request');

        $requestValidator  = $this->prophesize(ServerRequestValidator::class);
        $requestValidator->validate($request)->willThrow($exception);
        $requestValidator->getSchema()->willReturn($schema);

        $validatorBuilder = $this->prophesize(ValidatorBuilder::class);
        $validatorBuilder->getServerRequestValidator()->willReturn($requestValidator);

        $responseHandler = new RequestValidator($validatorBuilder->reveal());

        $result = $responseHandler->parse($request->reveal(), true);

        self::assertEquals(new RequestValidatorResult($schema, $operationAddress, $exception), $result);
    }

    public function testParseWithNoResource(): void
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn(new Uri('http://localhost:4010/test'));
        $request->getMethod()->willReturn('GET');

        $schema = new OpenApi([
            'openapi' => '3.0.2',
            'paths' => [],
        ]);
        $operationAddress = new OperationAddress('/test', 'GET');

        $exception = RoutingException::forNoResourceProvided(NoPath::fromPath('/test'));

        $requestValidator  = $this->prophesize(ServerRequestValidator::class);
        $requestValidator->validate($request)->willReturn($operationAddress)->shouldBeCalled();
        $requestValidator->getSchema()->willReturn($schema);

        $validatorBuilder = $this->prophesize(ValidatorBuilder::class);
        $validatorBuilder->getServerRequestValidator()->willReturn($requestValidator);

        $responseHandler = new RequestValidator($validatorBuilder->reveal());

        $result = $responseHandler->parse($request->reveal(), true);

        self::assertEquals(new RequestValidatorResult($schema, $operationAddress, $exception), $result);
    }
}

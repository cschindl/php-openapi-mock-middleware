<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Tests\Unit\Request;

use cebe\openapi\spec\OpenApi;
use Cschindl\OpenAPIMock\Exception\RoutingException;
use Cschindl\OpenAPIMock\Exception\SecurityException;
use Cschindl\OpenAPIMock\Exception\ValidationException;
use Cschindl\OpenAPIMock\Request\RequestHandler;
use Cschindl\OpenAPIMock\Response\ResponseFaker;
use Exception;
use League\OpenAPIValidation\PSR7\Exception\NoOperation;
use League\OpenAPIValidation\PSR7\Exception\NoPath;
use League\OpenAPIValidation\PSR7\Exception\NoResponseCode;
use League\OpenAPIValidation\PSR7\Exception\Validation\InvalidBody;
use League\OpenAPIValidation\PSR7\Exception\Validation\InvalidSecurity;
use League\OpenAPIValidation\PSR7\OperationAddress;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Vural\OpenAPIFaker\Exception\NoPath as FakerNoPath;

class RequestHandlerTest extends TestCase
{
    use ProphecyTrait;

    public function testHandleValidRequest(): void
    {
        $schema = new OpenApi([]);
        $operationAddress = new OperationAddress('/test', 'GET');
        $contentType = 'application/problem+json';

        $response = $this->prophesize(ResponseInterface::class);
        $responseFaker = $this->prophesize(ResponseFaker::class);
        $responseFaker->mock($schema, $operationAddress, ['200', '201'], $contentType, null)->willReturn($response);

        $responseHandler = new RequestHandler($responseFaker->reveal());

        $result = $responseHandler->handleValidRequest($schema, $operationAddress, $contentType, null, null);

        self::assertInstanceOf(ResponseInterface::class, $result);
    }

    public function testHandleValidRequestWithStatusCodeAndExample(): void
    {
        $schema = new OpenApi([]);
        $operationAddress = new OperationAddress('/test', 'GET');
        $contentType = 'application/problem+json';
        $statusCode = '400';
        $exampleName = 'testExample';

        $response = $this->prophesize(ResponseInterface::class);
        $responseFaker = $this->prophesize(ResponseFaker::class);
        $responseFaker->mock($schema, $operationAddress, $statusCode, $contentType, $exampleName)->willReturn($response);

        $responseHandler = new RequestHandler($responseFaker->reveal());

        $result = $responseHandler->handleValidRequest($schema, $operationAddress, $contentType, $statusCode, $exampleName);

        self::assertInstanceOf(ResponseInterface::class, $result);
    }

    /**
     * @param string[]|null $statusCodes
     *
     * @dataProvider provideHandleInValidRequestData
     */
    public function testHandleInValidRequest(Throwable $previous, array|null $statusCodes, Throwable|null $exception): void
    {
        $schema = new OpenApi([]);
        $operationAddress = new OperationAddress('/test', 'GET');
        $contentType = 'application/problem+json';

        $response = $this->prophesize(ResponseInterface::class);
        $responseFaker = $this->prophesize(ResponseFaker::class);
        $responseFaker->mock($schema, $operationAddress, $statusCodes, $contentType)->willReturn($response);
        $responseFaker->handleException($exception, $contentType)->willReturn($response);

        $responseHandler = new RequestHandler($responseFaker->reveal());

        $result = $responseHandler->handleInvalidRequest($previous, $schema, $operationAddress, $contentType);

        self::assertInstanceOf(ResponseInterface::class, $result);
    }

    /**
     * @return mixed[]
     */
    public function provideHandleInValidRequestData(): array
    {
        return [
            'NoPath' => [
                NoPath::fromPath('/test'),
                ['404', '400', '500', 'default'],
                null,
            ],
            'FakerNoPath' => [
                FakerNoPath::forPathAndMethod('/test', 'GET'),
                ['404', '400', '500', 'default'],
                null,
            ],
            'InvalidSecurity' => [
                InvalidSecurity::fromAddr(new OperationAddress('/test', 'GET')),
                ['401', '500', 'default'],
                null,
            ],
            'ValidationFailed' => [
                InvalidBody::becauseBodyIsNotValidJson('invalid json', new OperationAddress('/test', 'GET')),
                ['422', '400', '500', 'default'],
                null,
            ],
            'Throwable' => [
                new Exception('Invalid Request'),
                null,
                ValidationException::forViolations(new Exception('Invalid Request')),
            ],
        ];
    }

    public function testHandleNoPathMatchedRequest(): void
    {
        $previous = new Exception('Invalid request');
        $schema = new OpenApi([]);
        $operationAddress = new OperationAddress('/test', 'GET');
        $contentType = 'application/problem+json';

        $response = $this->prophesize(ResponseInterface::class);
        $responseFaker = $this->prophesize(ResponseFaker::class);
        $responseFaker->mock($schema, $operationAddress, ['404', '400', '500', 'default'], $contentType)->willReturn($response);

        $responseHandler = new RequestHandler($responseFaker->reveal());

        $result = $responseHandler->handleNoPathMatchedRequest($previous, $schema, $operationAddress, $contentType);

        self::assertInstanceOf(ResponseInterface::class, $result);
    }

    /**
     * @dataProvider provideHandleNoPathMatchedRequestWithoutMatchingStatusCodeData
     */
    public function testHandleNoPathMatchedRequestWithoutMatchingStatusCode(Throwable $previous, Throwable $exception): void
    {
        $schema = new OpenApi([]);
        $operationAddress = new OperationAddress('/test', 'GET');
        $contentType = 'application/problem+json';

        $response = $this->prophesize(ResponseInterface::class);
        $responseFaker = $this->prophesize(ResponseFaker::class);
        $responseFaker->mock($schema, $operationAddress, ['404', '400', '500', 'default'], $contentType)->willThrow(
            RoutingException::forNoResourceProvided($previous)
        );
        $responseFaker->handleException($exception, $contentType)->willReturn($response);

        $responseHandler = new RequestHandler($responseFaker->reveal());

        $result = $responseHandler->handleNoPathMatchedRequest($previous, $schema, $operationAddress, $contentType);

        self::assertInstanceOf(ResponseInterface::class, $result);
    }

    /**
     * @return mixed[]
     */
    public function provideHandleNoPathMatchedRequestWithoutMatchingStatusCodeData(): array
    {
        return [
            'NoResponseCode' => [
                NoResponseCode::fromPathAndMethodAndResponseCode('/test', 'GET', 404),
                RoutingException::forNoPathAndMethodAndResponseCodeMatched(NoResponseCode::fromPathAndMethodAndResponseCode('/test', 'GET', 404)),
            ],
            'NoOperation' => [
                NoOperation::fromPathAndMethod('/test', 'GET'),
                RoutingException::forNoPathAndMethodMatched(NoOperation::fromPathAndMethod('/test', 'GET')),
            ],
            'NoPath' => [
                NoPath::fromPath('/test'),
                RoutingException::forNoPathMatched(NoPath::fromPath('/test')),
            ],
            'FakerNoPath' => [
                FakerNoPath::forPathAndMethod('/test', 'GET'),
                RoutingException::forNoPathMatched(FakerNoPath::forPathAndMethod('/test', 'GET')),
            ],
            'Throwable' => [
                new Exception('Invalid Request'),
                ValidationException::forViolations(new Exception('Invalid Request')),
            ],
        ];
    }

    public function testHandleInvalidSecurityRequest(): void
    {
        $previous = new Exception('Invalid request');
        $schema = new OpenApi([]);
        $operationAddress = new OperationAddress('/test', 'GET');
        $contentType = 'application/problem+json';

        $response = $this->prophesize(ResponseInterface::class);
        $responseFaker = $this->prophesize(ResponseFaker::class);
        $responseFaker->mock($schema, $operationAddress, ['401', '500', 'default'], $contentType)->willReturn($response);

        $responseHandler = new RequestHandler($responseFaker->reveal());

        $result = $responseHandler->handleInvalidSecurityRequest($previous, $schema, $operationAddress, $contentType);

        self::assertInstanceOf(ResponseInterface::class, $result);
    }

    public function testHandleInvalidSecurityRequestWithoutMatchingStatusCode(): void
    {
        $previous = new Exception('Invalid request');
        $schema = new OpenApi([]);
        $operationAddress = new OperationAddress('/test', 'GET');
        $contentType = 'application/problem+json';

        $response = $this->prophesize(ResponseInterface::class);
        $responseFaker = $this->prophesize(ResponseFaker::class);
        $responseFaker->mock($schema, $operationAddress, ['401', '500', 'default'], $contentType)->willThrow(
            RoutingException::forNoResourceProvided($previous)
        );
        $responseFaker->handleException(SecurityException::forUnauthorized($previous), $contentType)->willReturn($response);

        $responseHandler = new RequestHandler($responseFaker->reveal());

        $result = $responseHandler->handleInvalidSecurityRequest($previous, $schema, $operationAddress, $contentType);

        self::assertInstanceOf(ResponseInterface::class, $result);
    }

    public function testHandleValidationFailedRequest(): void
    {
        $previous = new Exception('Invalid request');
        $schema = new OpenApi([]);
        $operationAddress = new OperationAddress('/test', 'GET');
        $contentType = 'application/problem+json';

        $response = $this->prophesize(ResponseInterface::class);
        $responseFaker = $this->prophesize(ResponseFaker::class);
        $responseFaker->mock($schema, $operationAddress, ['422', '400', '500', 'default'], $contentType)->willReturn($response);

        $responseHandler = new RequestHandler($responseFaker->reveal());

        $result = $responseHandler->handleValidationFailedRequest($previous, $schema, $operationAddress, $contentType);

        self::assertInstanceOf(ResponseInterface::class, $result);
    }

    public function testHandleValidationFailedRequestWithoutMatchingStatusCode(): void
    {
        $previous = new Exception('Invalid request');
        $schema = new OpenApi([]);
        $operationAddress = new OperationAddress('/test', 'GET');
        $contentType = 'application/problem+json';

        $response = $this->prophesize(ResponseInterface::class);
        $responseFaker = $this->prophesize(ResponseFaker::class);
        $responseFaker->mock($schema, $operationAddress, ['422', '400', '500', 'default'], $contentType)->willThrow(
            RoutingException::forNoResourceProvided($previous)
        );
        $responseFaker->handleException(ValidationException::forUnprocessableEntity($previous), $contentType)->willReturn($response);

        $responseHandler = new RequestHandler($responseFaker->reveal());

        $result = $responseHandler->handleValidationFailedRequest($previous, $schema, $operationAddress, $contentType);

        self::assertInstanceOf(ResponseInterface::class, $result);
    }
}

<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Tests\Unit\Response;

use Cschindl\OpenAPIMock\Exception\ValidationException;
use Cschindl\OpenAPIMock\Response\ResponseFaker;
use Cschindl\OpenAPIMock\Response\ResponseHandler;
use Exception;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ResponseInterface;

class ResponseHandlerTest extends TestCase
{
    use ProphecyTrait;

    public function testMockWithStatusCode(): void
    {
        $previous = new Exception();
        $contentType = 'application/json';

        $responseFaker = $this->prophesize(ResponseFaker::class);
        $responseFaker->handleException(ValidationException::forViolations($previous), $contentType)->willReturn(
            $this->prophesize(ResponseInterface::class)
        );

        $responseHandler = new ResponseHandler($responseFaker->reveal());

        $result = $responseHandler->handleInvalidResponse($previous, $contentType);

        self::assertInstanceOf(ResponseInterface::class, $result);
    }
}

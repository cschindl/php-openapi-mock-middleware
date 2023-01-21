<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Tests\Unit\Validator;

use Cschindl\OpenAPIMock\Validator\ResponseValidator;
use Cschindl\OpenAPIMock\Validator\ResponseValidatorResult;
use Exception;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ResponseValidator as PSR7ResponseValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ResponseInterface;

class ResponseValidatorTest extends TestCase
{
    use ProphecyTrait;

    public function testParseWithValidResponseWithoutValidate(): void
    {
        $response = $this->prophesize(ResponseInterface::class);
        $operationAddress = $this->prophesize(OperationAddress::class);

        $responseValidator = $this->prophesize(PSR7ResponseValidator::class);
        $responseValidator->validate($operationAddress, $response)->shouldNotBeCalled();

        $validatorBuilder = $this->prophesize(ValidatorBuilder::class);
        $validatorBuilder->getResponseValidator()->willReturn($responseValidator);

        $responseHandler = new ResponseValidator($validatorBuilder->reveal());

        $result = $responseHandler->parse($response->reveal(), $operationAddress->reveal(), false);

        self::assertEquals(new ResponseValidatorResult(), $result);
    }

    public function testParseWithValidResponseWithValidate(): void
    {
        $response = $this->prophesize(ResponseInterface::class);
        $operationAddress = $this->prophesize(OperationAddress::class);

        $responseValidator = $this->prophesize(PSR7ResponseValidator::class);
        $responseValidator->validate($operationAddress, $response)->shouldBeCalled();

        $validatorBuilder = $this->prophesize(ValidatorBuilder::class);
        $validatorBuilder->getResponseValidator()->willReturn($responseValidator);

        $responseHandler = new ResponseValidator($validatorBuilder->reveal());

        $result = $responseHandler->parse($response->reveal(), $operationAddress->reveal(), true);

        self::assertEquals(new ResponseValidatorResult(), $result);
    }

    public function testParseWithInValidResponse(): void
    {
        $response = $this->prophesize(ResponseInterface::class);
        $operationAddress = $this->prophesize(OperationAddress::class);

        $exception = new Exception('Invalid response');

        $responseValidator = $this->prophesize(PSR7ResponseValidator::class);
        $responseValidator->validate($operationAddress, $response)->willThrow($exception);

        $validatorBuilder = $this->prophesize(ValidatorBuilder::class);
        $validatorBuilder->getResponseValidator()->willReturn($responseValidator);

        $responseHandler = new ResponseValidator($validatorBuilder->reveal());

        $result = $responseHandler->parse($response->reveal(), $operationAddress->reveal(), true);

        self::assertEquals(new ResponseValidatorResult($exception), $result);
    }
}

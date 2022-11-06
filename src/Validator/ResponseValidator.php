<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Validator;

use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class ResponseValidator
{
    private ValidatorBuilder $validatorBuilder;

    public function __construct(
        ValidatorBuilder $validatorBuilder
    ) {
        $this->validatorBuilder = $validatorBuilder;
    }

    public function parse(ResponseInterface $response, OperationAddress $operationAddress, bool $validate): ResponseValidatorResult
    {
        $responseValidator = $this->validatorBuilder->getResponseValidator();

        if ($validate) {
            try {
                $responseValidator->validate($operationAddress, $response);
            } catch (Throwable $th) {
                return new ResponseValidatorResult($th);
            }
        }

        return new ResponseValidatorResult();
    }
}

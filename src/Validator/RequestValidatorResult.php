<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Validator;

use cebe\openapi\spec\OpenApi;
use League\OpenAPIValidation\PSR7\OperationAddress;
use Throwable;

class RequestValidatorResult
{
    private OpenApi $schema;

    private OperationAddress $operationAddress;

    private ?Throwable $exception;

    public function __construct(
        OpenApi $schema,
        OperationAddress $operationAddress,
        ?Throwable $exception = null
    ) {
        $this->schema = $schema;
        $this->operationAddress = $operationAddress;
        $this->exception = $exception;
    }

    public function getSchema(): OpenApi
    {
        return $this->schema;
    }

    public function getOperationAddress(): OperationAddress
    {
        return $this->operationAddress;
    }

    public function getException(): ?Throwable
    {
        return $this->exception;
    }

    public function isValid(): bool
    {
        return $this->exception === null;
    }
}

<?php

declare(strict_types=1);

namespace Cschindl\OpenApiMockMiddleware\Validator;

use cebe\openapi\spec\OpenApi;
use League\OpenAPIValidation\PSR7\OperationAddress;
use Throwable;

class RequestValidatorResult
{
    public function __construct(
        private OpenApi $schema,
        private OperationAddress $operationAddress,
        private Throwable|null $exception = null
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

    public function getException(): Throwable|null
    {
        return $this->exception;
    }

    public function isValid(): bool
    {
        return $this->exception === null;
    }
}

<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Validator;

use Throwable;

class ResponseValidatorResult
{
    public function __construct(
        private Throwable|null $exception = null
    ) {
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

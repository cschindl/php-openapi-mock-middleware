<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Validator;

use Throwable;

class ResponseValidatorResult
{
    private ?Throwable $exception;

    public function __construct(
        ?Throwable $exception = null
    ) {
        $this->exception = $exception;
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

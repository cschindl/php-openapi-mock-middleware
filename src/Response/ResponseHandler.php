<?php

declare(strict_types=1);

namespace Cschindl\OpenApiMockMiddleware\Response;

use Cschindl\OpenApiMockMiddleware\Exception\ValidationException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class ResponseHandler
{
    public function __construct(
        private ResponseFaker $responseFaker
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function handleInvalidResponse(Throwable $previous, string $contentType): ResponseInterface
    {
        return $this->responseFaker->handleException(ValidationException::forViolations($previous), $contentType);
    }
}

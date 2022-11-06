<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Response;

use Cschindl\OpenAPIMock\Exception\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class ResponseHandler
{
    private ResponseFaker $responseFaker;

    public function __construct(
        ResponseFaker $responseFaker
    ) {
        $this->responseFaker = $responseFaker;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function handleInvalidResponse(Throwable $previous, string $contentType): ResponseInterface
    {
        return $this->responseFaker->handleException(ValidationException::forViolations($previous), $contentType);
    }
}

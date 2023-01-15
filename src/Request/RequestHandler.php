<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Request;

use cebe\openapi\spec\OpenApi;
use Cschindl\OpenAPIMock\Exception\RoutingException;
use Cschindl\OpenAPIMock\Exception\SecurityException;
use Cschindl\OpenAPIMock\Exception\ValidationException;
use Cschindl\OpenAPIMock\Response\ResponseFaker;
use InvalidArgumentException;
use League\OpenAPIValidation\PSR7\Exception\NoOperation;
use League\OpenAPIValidation\PSR7\Exception\NoPath;
use League\OpenAPIValidation\PSR7\Exception\NoResponseCode;
use League\OpenAPIValidation\PSR7\Exception\Validation\InvalidSecurity;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\OperationAddress;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Vural\OpenAPIFaker\Exception\NoPath as FakerNoPath;

class RequestHandler
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
    public function handleValidRequest(
        OpenApi $schema,
        OperationAddress $operationAddress,
        string $contentType,
        ?string $statusCode = null,
        ?string $exampleName = null
    ): ResponseInterface {
        return $this->responseFaker->mock($schema, $operationAddress, $statusCode ?? ['200', '201'], $contentType, $exampleName);
    }

    /**
     * @throws InvalidArgumentException
     * @throws Throwable
     */
    public function handleInvalidRequest(
        Throwable $previous,
        OpenApi $schema,
        OperationAddress $operationAddress,
        string $contentType
    ): ResponseInterface {
        // ValidationException::forNotAcceptable
        // Message: The server cannot produce a representation for your accept header
        // Returned Status Code: 406
        // Explanation: This error occurs when the current request has asked the response in a format that the current document
        // is not able to produce.

        switch (true) {
            case $previous instanceof NoPath:
            case $previous instanceof FakerNoPath:
                return $this->handleNoPathMatchedRequest($previous, $schema, $operationAddress, $contentType);

            case $previous instanceof InvalidSecurity:
                return $this->handleInvalidSecurityRequest($previous, $schema, $operationAddress, $contentType);

            case $previous instanceof ValidationFailed:
                return $this->handleValidationFailedRequest($previous, $schema, $operationAddress, $contentType);

            default:
                return $this->responseFaker->handleException(ValidationException::forViolations($previous), $contentType);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function handleNoPathMatchedRequest(
        Throwable $previous,
        OpenApi $schema,
        OperationAddress $operationAddress,
        string $contentType
    ): ResponseInterface {
        try {
            return $this->responseFaker->mock($schema, $operationAddress, ['404', '400', '500', 'default'], $contentType);
        } catch (Throwable $th) {
            switch (true) {
                case $previous instanceof NoResponseCode:
                    $th = RoutingException::forNoPathAndMethodAndResponseCodeMatched($previous);
                    break;
                case $previous instanceof NoOperation:
                    $th = RoutingException::forNoPathAndMethodMatched($previous);
                    break;
                case $previous instanceof NoPath:
                case $previous instanceof FakerNoPath:
                    $th = RoutingException::forNoPathMatched($previous);
                    break;
                default:
                    $th = ValidationException::forViolations($previous);
            }

            return $this->responseFaker->handleException($th, $contentType);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function handleInvalidSecurityRequest(
        Throwable $previous,
        OpenApi $schema,
        OperationAddress $operationAddress,
        string $contentType
    ): ResponseInterface {
        try {
            return $this->responseFaker->mock($schema, $operationAddress, ['401', '500', 'default'], $contentType);
        } catch (Throwable) {
            return $this->responseFaker->handleException(SecurityException::forUnauthorized($previous), $contentType);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function handleValidationFailedRequest(
        Throwable $previous,
        OpenApi $schema,
        OperationAddress $operationAddress,
        string $contentType
    ): ResponseInterface {
        try {
            return $this->responseFaker->mock($schema, $operationAddress, ['422', '400', '500', 'default'], $contentType);
        } catch (Throwable) {
            return $this->responseFaker->handleException(ValidationException::forUnprocessableEntity($previous), $contentType);
        }
    }
}

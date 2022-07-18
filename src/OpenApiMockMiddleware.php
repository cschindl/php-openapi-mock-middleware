<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock;

use cebe\openapi\spec\OpenApi;
use Cschindl\OpenAPIMock\Exception\RoutingException;
use Cschindl\OpenAPIMock\Exception\SecurityException;
use Cschindl\OpenAPIMock\Exception\UnknownException;
use Cschindl\OpenAPIMock\Exception\ValidationException;
use InvalidArgumentException;
use League\OpenAPIValidation\PSR7\Exception\NoOperation;
use League\OpenAPIValidation\PSR7\Exception\NoPath;
use League\OpenAPIValidation\PSR7\Exception\NoResponseCode;
use League\OpenAPIValidation\PSR7\Exception\Validation\InvalidSecurity;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\OperationAddress;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class OpenApiMockMiddleware implements MiddlewareInterface
{
    /**
     * @var RequestValidator 
     */
    private $requestValidator;

    /**
     * @var ResponseValidator 
     */
    private $responseValidator;

    /**
     * @var ResponseFaker 
     */
    private $responseFaker;

    /**
     * @var ErrorResponseGenerator 
     */
    private $errorReponseHandler;

    /**
     * @param RequestValidator $requestValidator
     * @param ResponseValidator $responseValidator
     * @param ResponseFaker $responseFaker
     * @param ErrorResponseGenerator $errorReponseHandler
     */
    public function __construct(
        RequestValidator $requestValidator,
        ResponseValidator $responseValidator,
        ResponseFaker $responseFaker,
        ErrorResponseGenerator $errorReponseHandler
    ) {
        $this->requestValidator = $requestValidator;
        $this->responseValidator = $responseValidator;
        $this->responseFaker = $responseFaker;
        $this->errorReponseHandler = $errorReponseHandler;
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $statusCode = "200";
        $contentType = 'application/json';

        // TODO
        // ValidationException::forNotFound
        // Message: The server cannot find the requested content
        // Returned Status Code: 404
        // Explanation: This error occurs when the current request is asking for a specific status code that the document is not listing
        // or it's asking for a specific example that does not exist in the current document

        try {
            $operationAddress = $this->requestValidator->validateRequest($request);
            $schema = $this->requestValidator->getSchema();

            $response = $this->handleValidRequest($schema, $operationAddress, $contentType);

            try {
                $this->responseValidator->validateResonse($operationAddress, $response);
            } catch (Throwable $th) {
                $response = $this->handleInvalidResponse($th, $contentType);
            }
        } catch (Throwable $th) {
            $operationAddress = new OperationAddress($request->getUri()->getPath(), $request->getMethod());
            $schema = $this->requestValidator->getSchema();

            $response = $this->handleInvalidRequest($th, $schema, $operationAddress, $contentType);
        }

        return $response;
    }

    /**
     * @param OpenApi $schema
     * @param OperationAddress $operationAddress
     * @param string $contentType
     * @return ResponseInterface
     * @throws InvalidArgumentException
     */
    private function handleValidRequest(OpenApi $schema, OperationAddress $operationAddress, string $contentType): ResponseInterface
    {
        try {
            return $this->responseFaker->mockPossibleResponse($schema, $operationAddress, ['200', '201'], $contentType);
        } catch (Throwable $th) {
            return $this->errorReponseHandler->handleException(UnknownException::forUnexpectedErrorOccurred($th), $contentType);
        }
    }

    /**
     * @param Throwable $th
     * @param OpenApi $schema
     * @param OperationAddress $operationAddress
     * @param string $contentType
     * @return ResponseInterface
     * @throws InvalidArgumentException
     * @throws Throwable
     */
    private function handleInvalidRequest(Throwable $th, OpenApi $schema, OperationAddress $operationAddress, string $contentType): ResponseInterface
    {
        // ValidationException::forNotAcceptable
        // Message: The server cannot produce a representation for your accept header
        // Returned Status Code: 406
        // Explanation: This error occurs when the current request has asked the response in a format that the current document is not able to produce.

        switch (true) {
            case $th instanceof NoPath:
                return $this->handleNoOperationRequest($th, $schema, $operationAddress, $contentType);
            case $th instanceof InvalidSecurity:
                return $this->handleInvalidSecurityRequest($th, $schema, $operationAddress, $contentType);
            case $th instanceof ValidationFailed:
                return $this->handleValidationFailedRequest($th, $schema, $operationAddress, $contentType);
            default:
                return $this->errorReponseHandler->handleException(ValidationException::forViolations($th), $contentType);
        }
    }

    /**
     * @param Throwable $previous
     * @param OpenApi $schema
     * @param OperationAddress $operationAddress
     * @param string $contentType
     * @return ResponseInterface
     * @throws InvalidArgumentException
     */
    private function handleNoOperationRequest(Throwable $previous, OpenApi $schema, OperationAddress $operationAddress, string $contentType): ResponseInterface
    {
        try {
            return $this->responseFaker->mockPossibleResponse($schema, $operationAddress, ['404', '400', '500', 'default'], $contentType);
        } catch (Throwable $th) {
            switch (true) {
                case $previous instanceof NoResponseCode:
                    $th = RoutingException::forNoPathAndMethodAndResponseCodeMatched($previous);
                    break;
                case $previous instanceof NoOperation:
                    $th = RoutingException::forNoPathAndMethodMatched($previous);
                    break;
                case $previous instanceof NoPath:
                    $th = RoutingException::forNoPathMatched($previous);
                    break;
                default:
                    $th = ValidationException::forViolations($previous);
            }

            return $this->errorReponseHandler->handleException($th, $contentType);
        }
    }

    /**
     * @param Throwable $previous
     * @param OpenApi $schema
     * @param OperationAddress $operationAddress
     * @param string $contentType
     * @return ResponseInterface
     * @throws InvalidArgumentException
     */
    private function handleInvalidSecurityRequest(Throwable $previous, OpenApi $schema, OperationAddress $operationAddress, string $contentType): ResponseInterface
    {
        try {
            return $this->responseFaker->mockPossibleResponse($schema, $operationAddress, ['401', '500', 'default'], $contentType);
        } catch (Throwable $th) {
            return $this->errorReponseHandler->handleException(SecurityException::forUnauthorized($previous), $contentType);
        }
    }

    /**
     * @param Throwable $previous
     * @param OpenApi $schema
     * @param OperationAddress $operationAddress
     * @param string $contentType
     * @return ResponseInterface
     * @throws InvalidArgumentException
     */
    private function handleValidationFailedRequest(Throwable $previous, OpenApi $schema, OperationAddress $operationAddress, string $contentType): ResponseInterface
    {
        try {
            return $this->responseFaker->mockPossibleResponse($schema, $operationAddress, ['422', '400', '500', 'default'], $contentType);
        } catch (Throwable $th) {
            return $this->errorReponseHandler->handleException(ValidationException::forUnprocessableEntity($previous), $contentType);
        }
    }

    /**
     * @param Throwable $previous
     * @param string $contentType
     * @return ResponseInterface
     * @throws InvalidArgumentException
     */
    private function handleInvalidResponse(Throwable $previous, string $contentType): ResponseInterface
    {
        return $this->errorReponseHandler->handleException(ValidationException::forViolations($previous), $contentType);
    }
}

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
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Vural\OpenAPIFaker\Exception\NoExample;

class OpenApiMockMiddleware implements MiddlewareInterface
{
    private const HEADER_CONTENT_TYPE = 'Content-Type';
    private const HEADER_FAKER_STATUSCODE = 'X-Faker-Statuscode';
    private const HEADER_FAKER_EXAMPLE = 'X-Faker-Example';
    private const DEFAULT_CONTENT_TYPE = 'application/json';

    private ValidatorBuilder $validatorBuilder;

    private ResponseFaker $responseFaker;

    private ErrorResponseGenerator $errorReponseHandler;

    public function __construct(
        ValidatorBuilder $validatorBuilder,
        ResponseFaker $responseFaker,
        ErrorResponseGenerator $errorReponseHandler
    ) {
        $this->validatorBuilder = $validatorBuilder;
        $this->responseFaker = $responseFaker;
        $this->errorReponseHandler = $errorReponseHandler;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $statusCode = $this->getStatusCode($request);
        $contentType = $this->getContentType($request);
        $exampleName = $this->getExample($request);

        try {
            $requestValidator = $this->validatorBuilder->getServerRequestValidator();
            $operationAddress = $requestValidator->validate($request);
            $schema = $requestValidator->getSchema();

            if (!isset($schema->paths) || empty($schema->paths)) {
                throw RoutingException::forNoResourceProvided(NoPath::fromPath($request->getUri()->getPath()));
            }

            $response = $this->handleValidRequest($schema, $operationAddress, $contentType, $statusCode, $exampleName);

            try {
                $responseValidator = $this->validatorBuilder->getResponseValidator();
                $responseValidator->validate($operationAddress, $response);
            } catch (Throwable $th) {
                $response = $this->handleInvalidResponse($th, $contentType);
            }
        } catch (Throwable $th) {
            $requestValidator = $this->validatorBuilder->getServerRequestValidator();
            $operationAddress = new OperationAddress($request->getUri()->getPath(), $request->getMethod());
            $schema = $requestValidator->getSchema();

            $response = $this->handleInvalidRequest($th, $schema, $operationAddress, $contentType);
        }

        return $response;
    }

    private function getStatusCode(ServerRequestInterface $request): ?string
    {
        $statusCode = $request->getHeader(self::HEADER_FAKER_STATUSCODE)[0] ?? null;

        return !empty($statusCode) ? $statusCode : null;
    }

    private function getContentType(ServerRequestInterface $request): string
    {
        $statusCode = $request->getHeader(self::HEADER_CONTENT_TYPE)[0] ?? self::DEFAULT_CONTENT_TYPE;

        return !empty($statusCode) ? $statusCode : self::DEFAULT_CONTENT_TYPE;
    }

    private function getExample(ServerRequestInterface $request): ?string
    {
        $statusCode = $request->getHeader(self::HEADER_FAKER_EXAMPLE)[0] ?? null;

        return !empty($statusCode) ? $statusCode : null;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function handleValidRequest(
        OpenApi $schema,
        OperationAddress $operationAddress,
        string $contentType,
        ?string $statusCode = null,
        ?string $exampleName = null
    ): ResponseInterface {
        try {
            return $this->responseFaker->mockPossibleResponse($schema, $operationAddress, $statusCode ?? ['200', '201'], $contentType, $exampleName);
        } catch (NoExample $th) {
            return $this->errorReponseHandler->handleException(ValidationException::forNotFound($th), $contentType);
        } catch (Throwable $th) {
            return $this->errorReponseHandler->handleException(UnknownException::forUnexpectedErrorOccurred($th), $contentType);
        }
    }

    /**
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
     * @throws InvalidArgumentException
     */
    private function handleInvalidResponse(Throwable $previous, string $contentType): ResponseInterface
    {
        return $this->errorReponseHandler->handleException(ValidationException::forViolations($previous), $contentType);
    }
}

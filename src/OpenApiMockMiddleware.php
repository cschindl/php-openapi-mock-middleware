<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock;

use Cschindl\OpenAPIMock\Request\RequestHandler;
use Cschindl\OpenAPIMock\Validator\RequestValidator;
use Cschindl\OpenAPIMock\Response\ResponseHandler;
use Cschindl\OpenAPIMock\Validator\ResponseValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class OpenApiMockMiddleware implements MiddlewareInterface
{
    public const HEADER_CONTENT_TYPE = 'Content-Type';
    public const HEADER_FAKER_ACTIVE = 'X-Faker-Active';
    public const HEADER_FAKER_STATUSCODE = 'X-Faker-StatusCode';
    public const HEADER_FAKER_EXAMPLE = 'X-Faker-Example';
    public const DEFAULT_CONTENT_TYPE = 'application/json';

    private RequestHandler $requestHandler;

    private RequestValidator $requestValidator;

    private ResponseHandler $responseHandler;

    private ResponseValidator $responseValidator;

    public function __construct(
        RequestHandler $requestHandler,
        RequestValidator $requestValidator,
        ResponseHandler $responseHandler,
        ResponseValidator $responseValidator
    ) {
        $this->requestHandler = $requestHandler;
        $this->requestValidator = $requestValidator;
        $this->responseHandler = $responseHandler;
        $this->responseValidator = $responseValidator;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $isActive = $this->isActive($request);
        $statusCode = $this->getStatusCode($request);
        $contentType = $this->getContentType($request);
        $exampleName = $this->getExample($request);

        $validateRequest = false;
        $validateResponse = false;

        if (!$isActive) {
            return $handler->handle($request);
        }

        $requestResult = $this->requestValidator->parse($request, $validateRequest);

        try {
            if (!$requestResult->isValid()) {
                throw $requestResult->getException();
            }

            $response = $this->requestHandler->handleValidRequest(
                $requestResult->getSchema(),
                $requestResult->getOperationAddress(),
                $contentType,
                $statusCode,
                $exampleName
            );

            $responseResult = $this->responseValidator->parse(
                $response,
                $requestResult->getOperationAddress(),
                $validateResponse
            );

            if ($responseResult->isValid()) {
                return $response;
            }

            return $this->responseHandler->handleInvalidResponse($responseResult->getException(), $contentType);
        } catch (Throwable $th) {
            return $this->requestHandler->handleInvalidRequest(
                $th,
                $requestResult->getSchema(),
                $requestResult->getOperationAddress(),
                $contentType
            );
        }
    }

    private function isActive(ServerRequestInterface $request): bool
    {
        $isActive = $request->getHeader(self::HEADER_FAKER_ACTIVE)[0] ?? false;

        return (bool) $isActive;
    }

    private function getStatusCode(ServerRequestInterface $request): ?string
    {
        $statusCode = $request->getHeader(self::HEADER_FAKER_STATUSCODE)[0] ?? null;

        return !empty($statusCode) ? $statusCode : null;
    }

    private function getContentType(ServerRequestInterface $request): string
    {
        $contentType = $request->getHeader(self::HEADER_CONTENT_TYPE)[0] ?? self::DEFAULT_CONTENT_TYPE;

        return !empty($contentType) ? $contentType : self::DEFAULT_CONTENT_TYPE;
    }

    private function getExample(ServerRequestInterface $request): ?string
    {
        $example = $request->getHeader(self::HEADER_FAKER_EXAMPLE)[0] ?? null;

        return !empty($example) ? $example : null;
    }
}

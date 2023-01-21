<?php

declare(strict_types=1);

namespace Cschindl\OpenApiMockMiddleware;

use Cschindl\OpenApiMockMiddleware\Request\RequestHandler;
use Cschindl\OpenApiMockMiddleware\Response\ResponseHandler;
use Cschindl\OpenApiMockMiddleware\Validator\RequestValidator;
use Cschindl\OpenApiMockMiddleware\Validator\ResponseValidator;
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

    public function __construct(
        private RequestHandler $requestHandler,
        private RequestValidator $requestValidator,
        private ResponseHandler $responseHandler,
        private ResponseValidator $responseValidator,
        private OpenApiMockMiddlewareConfig $config
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $isActive = $this->isActive($request);
        $statusCode = $this->getStatusCode($request);
        $contentType = $this->getContentType($request);
        $exampleName = $this->getExample($request);

        if (!$isActive) {
            return $handler->handle($request);
        }

        $requestResult = $this->requestValidator->parse($request, $this->config->validateRequest());

        try {
            if ($requestResult->getException() instanceof Throwable) {
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
                $this->config->validateResponse()
            );

            if ($responseResult->getException() instanceof Throwable) {
                return $this->responseHandler->handleInvalidResponse($responseResult->getException(), $contentType);
            }

            return $response;
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

    private function getStatusCode(ServerRequestInterface $request): string|null
    {
        $statusCode = $request->getHeader(self::HEADER_FAKER_STATUSCODE)[0] ?? null;

        return !empty($statusCode) ? $statusCode : null;
    }

    private function getContentType(ServerRequestInterface $request): string
    {
        $contentType = $request->getHeader(self::HEADER_CONTENT_TYPE)[0] ?? self::DEFAULT_CONTENT_TYPE;

        return !empty($contentType) ? $contentType : self::DEFAULT_CONTENT_TYPE;
    }

    private function getExample(ServerRequestInterface $request): string|null
    {
        $example = $request->getHeader(self::HEADER_FAKER_EXAMPLE)[0] ?? null;

        return !empty($example) ? $example : null;
    }
}

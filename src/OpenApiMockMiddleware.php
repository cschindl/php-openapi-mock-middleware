<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock;

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

        try {
            $operationAddress = $this->requestValidator->validateRequest($request);
            $schema = $this->requestValidator->getSchema();

            $response = $this->responseFaker->mockResponse($schema, $operationAddress, $statusCode, $contentType);

            $this->responseValidator->validateResonse($operationAddress, $response);

            return $response;
        } catch (Throwable $th) {
            return $this->errorReponseHandler->handleException($th, $request, $contentType);
        }
    }
}

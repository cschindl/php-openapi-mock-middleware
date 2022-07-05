<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Vural\OpenAPIFaker\Exception\NoPath;

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
     * @param RequestValidator $requestValidator
     * @param ResponseValidator $responseValidator
     * @param ResponseFaker $responseFaker
     */
    public function __construct(
        RequestValidator $requestValidator,
        ResponseValidator $responseValidator,
        ResponseFaker $responseFaker
    ) {
        $this->requestValidator = $requestValidator;
        $this->responseValidator = $responseValidator;
        $this->responseFaker = $responseFaker;
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
            return $this->handleException($th, $request, $contentType);
        }
    }

    /**
     * @param Throwable $th
     * @param ServerRequestInterface $request
     * @param string|null $contentType
     * @return ResponseInterface
     * @throws InvalidArgumentException
     */
    private function handleException(Throwable $th, ServerRequestInterface $request, ?string $contentType): ResponseInterface
    {
        $statusCode = 500;
        $error = [];

        switch (get_class($th)) {
            case NoPath::class:
                $error = [
                    "type" => "NO_PATH_MATCHED_ERROR",
                    "title" => "Route not resolved, no path matched",
                    "detail" => sprintf("The route %s hasn't been found in the specification file", $request->getUri()->getPath()),
                    "status" => 404,
                ];
                $statusCode = 404;
                break;
            default:
                $error = [
                    "type" => "ERROR",
                    "title" => "Unexpected error occurred",
                    "detail" => $th->getMessage(),
                    "status" => 500,
                ];
                $statusCode = 500;
                break;
        }

        $response = $this->responseFactory->createResponse();
        $body = $this->streamFactory->createStream(json_encode($error));

        return $response->withBody($body)->withStatus($statusCode)->withAddedHeader('Content-Type', $contentType ?? 'application/json');
    }
}

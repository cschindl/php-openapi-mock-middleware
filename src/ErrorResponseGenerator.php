<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock;

use InvalidArgumentException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Throwable;
use Vural\OpenAPIFaker\Exception\NoPath;

class ErrorResponseGenerator
{
    /**
     * @var ResponseFactoryInterface
     */
    private $responseFactory;

    /**
     * @var StreamFactoryInterface
     */
    private $streamFactory;

    /**
     * @param ResponseFactoryInterface $responseFactory
     * @param StreamFactoryInterface $streamFactory
     */
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory
    ) {
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
    }

    /**
     * @param Throwable $th
     * @param ServerRequestInterface $request
     * @param string|null $contentType
     * @return ResponseInterface
     * @throws InvalidArgumentException
     */
    public function handleException(Throwable $th, ServerRequestInterface $request, ?string $contentType): ResponseInterface
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

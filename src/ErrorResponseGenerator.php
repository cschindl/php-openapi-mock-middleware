<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock;

use Cschindl\OpenAPIMock\Exception\RFC7807Interface;
use InvalidArgumentException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Throwable;

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
     * @param string|null $contentType
     * @return ResponseInterface
     * @throws InvalidArgumentException
     */
    public function handleException(Throwable $th, ?string $contentType): ResponseInterface
    {
        if ($th instanceof RFC7807Interface) {
            $error = [
                "type" => $th->getType(),
                "title" => $th->getTitle(),
                "detail" => $th->getMessage(),
                "status" => $th->getCode(),
            ];
            $statusCode =  $th->getCode();
        } else {
            $error = [
                "type" => "ERROR",
                "title" => "Unexpected error occurred",
                "detail" => $th->getMessage(),
                "status" => 500,
            ];
            $statusCode = 500;
        }

        $response = $this->responseFactory->createResponse();
        $body = $this->streamFactory->createStream(json_encode($error));

        return $response->withBody($body)->withStatus($statusCode)->withAddedHeader('Content-Type', $contentType ?? 'application/problem+json');
    }
}

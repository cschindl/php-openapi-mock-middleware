<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock;

use Cschindl\OpenAPIMock\Exception\NoSchemaFileFound;
use InvalidArgumentException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Vural\OpenAPIFaker\Exception\NoPath;
use Vural\OpenAPIFaker\OpenAPIFaker;

class OpenApiMockMiddleware implements MiddlewareInterface
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
     */
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory
    ) {
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $pathToYaml = __DIR__ . '/../../php-openapi-faker/tests/specs/petstore.yaml';
            if (!file_exists($pathToYaml)) {
                throw NoSchemaFileFound::forFilename($pathToYaml);
            }

            $yaml = file_get_contents($pathToYaml);
            $faker = OpenAPIFaker::createFromYaml($yaml);

            $path = $request->getUri()->getPath();
            $query = (!empty($request->getUri()->getQuery()) ? '?' . $request->getUri()->getQuery() : '');
            $method =  $request->getMethod();
            $statusCode = "200";
            $contentType = 'application/json';

            $fakeData = $faker->mockResponse($path . $query, $method, $statusCode, $contentType);
        } catch (Throwable $th) {
            return $this->handleException($th, $request, $contentType);
        }

        return $this->handleSuccess($fakeData, $contentType);
    }

    /**
     * @param array $data
     * @param string $contentType
     * @return ResponseInterface
     * @throws InvalidArgumentException
     */
    private function handleSuccess(array $data, string $contentType): ResponseInterface
    {
        $response = $this->responseFactory->createResponse();
        $body = $this->streamFactory->createStream(json_encode($data));

        return $response->withBody($body)->withAddedHeader('Content-Type', $contentType);
    }

    /**
     * @param Throwable $th
     * @param ServerRequestInterface $request
     * @param string $contentType
     * @return ResponseInterface
     * @throws InvalidArgumentException
     */
    private function handleException(Throwable $th, ServerRequestInterface $request, string $contentType): ResponseInterface
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

        return $response->withBody($body)->withStatus($statusCode)->withAddedHeader('Content-Type', $contentType);
    }
}

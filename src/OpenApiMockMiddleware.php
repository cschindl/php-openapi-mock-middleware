<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock;

use cebe\openapi\spec\OpenApi;
use Cschindl\OpenAPIMock\Exception\NoSchemaFileFound;
use Cschindl\OpenAPIMock\Exception\Routing;
use InvalidArgumentException;
use League\OpenAPIValidation\PSR7\OperationAddress;
use Psr\Cache\CacheItemPoolInterface;
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
     * @var CacheItemPoolInterface|null
     */
    private $cache;

    /**
     * @var string
     */
    private string $pathToYaml;

    /**
     * @var array
     */
    private $options;

    /**
     * @var OperationAddress 
     */
    private $requestOperation;

    /**
     * @var OpenApi 
     */
    private $schema;

    /**
     * @param ResponseFactoryInterface $responseFactory
     * @param StreamFactoryInterface $streamFactory
     * @param CacheItemPoolInterface|null $cache
     * @param string $pathToYaml
     * @param array $options
     */
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        ?CacheItemPoolInterface $cache = null,
        string $pathToYaml,
        array $options
    ) {
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
        $this->cache = $cache;
        $this->pathToYaml = $pathToYaml;
        $this->options = $options;
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
            $this->validateRequest($request);

            $response = $this->processRequest($statusCode, $contentType);

            $this->validateResponse($response);

            return $response;
        } catch (Throwable $th) {
            throw $th;
            return $this->handleException($th, $request, $contentType);
        }
    }

    private function validateRequest(ServerRequestInterface $request): void
    {
        if (!file_exists($this->pathToYaml)) {
            throw NoSchemaFileFound::forFilename($this->pathToYaml);
        }

        $yaml = file_get_contents($this->pathToYaml);
        $builder = (new \League\OpenAPIValidation\PSR7\ValidatorBuilder)->fromYaml($yaml);

        if ($this->cache instanceof CacheItemPoolInterface) {
            $builder = $builder->setCache($this->cache);
        }

        $validator = $builder->getServerRequestValidator();

        $schema = $validator->getSchema();
        if (!isset($schema->servers) || empty($schema->servers) || $schema->servers[0]->url === '/') {
            throw Routing::forNoServerMatched();
        }
        if (!isset($schema->paths) || empty($schema->paths)) {
            throw Routing::forNoResourceProvided();
        }

        $this->requestOperation = $validator->validate($request);
        $this->schema = $validator->getSchema();
    }

    private function processRequest(string $statusCode = '200', string $contentType = 'application/json'): ResponseInterface
    {
        $faker = OpenAPIFaker::createFromSchema($this->schema);
        $faker->setOptions($this->options);

        $path = $this->requestOperation->path();
        $method = $this->requestOperation->method();;

        $fakeData = $faker->mockResponse($path, $method, $statusCode, $contentType);

        $response = $this->responseFactory->createResponse();
        $body = $this->streamFactory->createStream(json_encode($fakeData));

        return $response->withBody($body)->withAddedHeader('Content-Type', $contentType);
    }

    private function validateResponse(ResponseInterface $response): void
    {
        if (!file_exists($this->pathToYaml)) {
            throw NoSchemaFileFound::forFilename($this->pathToYaml);
        }

        $yaml = file_get_contents($this->pathToYaml);
        $builder = (new \League\OpenAPIValidation\PSR7\ValidatorBuilder)->fromYaml($yaml);

        if ($this->cache instanceof CacheItemPoolInterface) {
            $builder = $builder->setCache($this->cache);
        }

        $validator = $builder->getResponseValidator();

        $validator->validate($this->requestOperation, $response);
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

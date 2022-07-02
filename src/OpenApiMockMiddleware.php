<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock;

use cebe\openapi\exceptions\TypeErrorException;
use cebe\openapi\exceptions\UnresolvableReferenceException;
use Cschindl\OpenAPIMock\Exception\NoSchemaFileFound;
use InvalidArgumentException;
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
        try {
            $faker = $this->createFaker();
            $faker->setOptions($this->options);

            $path = htmlentities($request->getUri()->getPath());
            $query = (!empty($request->getUri()->getQuery()) ? '?' . $request->getUri()->getQuery() : '');
            $method =  $request->getMethod();
            $statusCode = "200";
            $contentType = 'application/json';

            $fakeData = $faker->mockResponse($path . $query, $method, $statusCode, $contentType);
        } catch (Throwable $th) {
            return $this->handleException($th, $request, $contentType ?? 'application/json');
        }

        return $this->handleSuccess($fakeData, $contentType);
    }

    /**
     * @return OpenAPIFaker
     * @throws NoSchemaFileFound
     * @throws TypeErrorException
     * @throws UnresolvableReferenceException
     */
    private function createFaker(): OpenAPIFaker
    {
        if (!file_exists($this->pathToYaml)) {
            throw NoSchemaFileFound::forFilename($this->pathToYaml);
        }

        if ($this->cache !== null) {
            $cacheItem =  $this->cache->getItem(md5_file($this->pathToYaml));
            if ($cacheItem->isHit()) {
                return $cacheItem->get();
            }
        }

        $faker = OpenAPIFaker::createFromYaml(file_get_contents($this->pathToYaml));

        if ($cacheItem !== null) {
            $cacheItem->set($faker);
            $this->cache->save($cacheItem);
        }

        return $faker;
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

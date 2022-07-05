<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock;

use cebe\openapi\spec\OpenApi;
use InvalidArgumentException;
use League\OpenAPIValidation\PSR7\OperationAddress;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Vural\OpenAPIFaker\Exception\NoPath;
use Vural\OpenAPIFaker\Exception\NoResponse;
use Vural\OpenAPIFaker\OpenAPIFaker;

class ResponseFaker
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
     * @var array
     */
    private $fakerOptions;

    /**
     * @var OpenAPIFaker
     */
    private $faker;

    /**
     * @param ResponseFactoryInterface $responseFactory
     * @param StreamFactoryInterface $streamFactory
     * @param array $fakerOptions
     */
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        array $fakerOptions
    ) {
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
        $this->fakerOptions = $fakerOptions;
    }

    /**
     * @param OpenApi $schema
     * @param OperationAddress $operationAddress
     * @param string $statusCode
     * @param string $contentType
     * @return ResponseInterface
     * @throws NoPath
     * @throws NoResponse
     * @throws InvalidArgumentException
     */
    public function mockResponse(
        OpenApi $schema,
        OperationAddress $operationAddress,
        string $statusCode = '200',
        string $contentType = 'application/json'
    ): ResponseInterface {
        $faker = $this->createFaker($schema);

        $path = $operationAddress->path();
        $method = $operationAddress->method();;

        $fakeData = $faker->mockResponse($path, $method, $statusCode, $contentType);

        $response = $this->responseFactory->createResponse();
        $body = $this->streamFactory->createStream(json_encode($fakeData));

        return $response->withBody($body)->withAddedHeader('Content-Type', $contentType);
    }

    /**
     * @param OpenApi $schema
     * @return OpenAPIFaker
     */
    private function createFaker(OpenApi $schema): OpenAPIFaker
    {
        if ($this->faker instanceof OpenAPIFaker) {
            return $this->faker;
        }

        $this->faker = OpenAPIFaker::createFromSchema($schema);
        $this->faker->setOptions($this->fakerOptions);

        return $this->faker;
    }
}

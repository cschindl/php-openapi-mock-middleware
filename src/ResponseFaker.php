<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock;

use cebe\openapi\spec\OpenApi;
use InvalidArgumentException;
use League\OpenAPIValidation\PSR7\OperationAddress;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Vural\OpenAPIFaker\Exception\NoExample;
use Vural\OpenAPIFaker\Exception\NoPath;
use Vural\OpenAPIFaker\Exception\NoResponse;
use Vural\OpenAPIFaker\OpenAPIFaker;

use function array_shift;
use function is_string;
use function json_encode;

class ResponseFaker
{
    private ResponseFactoryInterface $responseFactory;

    private StreamFactoryInterface $streamFactory;

    /** @var array{minItems?: int|null, maxItems?: int|null, alwaysFakeOptionals?: bool, strategy?: string} */
    private array $fakerOptions;

    private ?OpenAPIFaker $faker = null;

    /**
     * @param array{minItems?: int|null, maxItems?: int|null, alwaysFakeOptionals?: bool, strategy?: string} $fakerOptions
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
     * @param array<int, string>|string $statusCodes
     *
     * @throws NoPath
     * @throws NoResponse
     * @throws NoExample
     * @throws InvalidArgumentException
     */
    public function mockPossibleResponse(
        OpenApi $schema,
        OperationAddress $operationAddress,
        $statusCodes,
        string $contentType = 'application/json',
        ?string $exampleName = null
    ): ResponseInterface {
        if (is_string($statusCodes)) {
            $statusCodes = [$statusCodes];
        }

        /** @var string $statusCode */
        $statusCode = array_shift($statusCodes);

        try {
            return $this->mockResponse($schema, $operationAddress, $statusCode, $contentType, $exampleName);
        } catch (NoResponse | NoPath | NoExample $th) {
            if (empty($statusCodes)) {
                throw $th;
            }

            return $this->mockPossibleResponse($schema, $operationAddress, $statusCodes, $contentType, $exampleName);
        }
    }

    /**
     * @throws NoPath
     * @throws NoResponse
     * @throws NoExample
     * @throws InvalidArgumentException
     */
    private function mockResponse(
        OpenApi $schema,
        OperationAddress $operationAddress,
        string $statusCode = '200',
        string $contentType = 'application/json',
        ?string $exampleName = null
    ): ResponseInterface {
        $faker = $this->createFaker($schema);

        $path = $operationAddress->path();
        $method = $operationAddress->method();

        $fakeData = $exampleName !== null
            ? $faker->mockResponseForExample($path, $method, $exampleName, $statusCode, $contentType)
            : $faker->mockResponse($path, $method, $statusCode, $contentType);

        $response = $this->responseFactory->createResponse();
        $body = $this->streamFactory->createStream((string) json_encode($fakeData));

        return $response->withBody($body)->withAddedHeader('Content-Type', $contentType)->withStatus((int) $statusCode);
    }

    private function createFaker(OpenApi $schema): OpenAPIFaker
    {
        if ($this->faker instanceof OpenAPIFaker) {
            return $this->faker;
        }

        $this->faker = OpenAPIFaker::createFromSchema($schema)->setOptions($this->fakerOptions);

        return $this->faker;
    }
}

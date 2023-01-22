<?php

declare(strict_types=1);

namespace Cschindl\OpenApiMockMiddleware\Response;

use cebe\openapi\spec\OpenApi;
use Cschindl\OpenApiMockMiddleware\Exception\RequestException;
use InvalidArgumentException;
use League\OpenAPIValidation\PSR7\OperationAddress;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Throwable;
use Vural\OpenAPIFaker\Exception\NoExample;
use Vural\OpenAPIFaker\Exception\NoPath;
use Vural\OpenAPIFaker\Exception\NoResponse;
use Vural\OpenAPIFaker\OpenAPIFaker;
use Vural\OpenAPIFaker\Options;

use function array_filter;
use function array_shift;
use function is_string;
use function json_encode;

class ResponseFaker
{
    private OpenAPIFaker|null $faker = null;

    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private Options $options
    ) {
    }

    /**
     * @param array<int, string>|string $statusCodes
     *
     * @throws NoPath
     * @throws NoResponse
     * @throws NoExample
     * @throws InvalidArgumentException
     */
    public function mock(
        OpenApi $schema,
        OperationAddress $operationAddress,
        array|string $statusCodes,
        string $contentType = 'application/json',
        string|null $exampleName = null
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

            return $this->mock($schema, $operationAddress, $statusCodes, $contentType, $exampleName);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function handleException(Throwable $th, string|null $contentType): ResponseInterface
    {
        if ($th instanceof RequestException) {
            $error = [
                'type' => $th->getType(),
                'title' => $th->getTitle(),
                'detail' => $th->getMessage(),
                'status' => $th->getCode(),
            ];
            $statusCode =  $th->getCode();
        } else {
            $error = [
                'type' => 'ERROR',
                'title' => 'Unexpected error occurred',
                'detail' => $th->getMessage(),
                'status' => 500,
            ];
            $statusCode = 500;
        }

        $response = $this->responseFactory->createResponse();
        $body = $this->streamFactory->createStream((string) json_encode($error));

        return $response->withBody($body)->withStatus($statusCode)->withAddedHeader('Content-Type', $contentType ?? 'application/problem+json');
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
        string|null $exampleName = null
    ): ResponseInterface {
        $faker = $this->createFaker($schema);

        $path = $operationAddress->path();
        $method = $operationAddress->method();

        $fakeData = $exampleName !== null
            ? $faker->mockResponseForExample($path, $method, $exampleName, $statusCode, $contentType)
            : $faker->mockResponse($path, $method, $statusCode, $contentType);

        $response = $this->responseFactory->createResponse();
        $body = $this->streamFactory->createStream((string) json_encode($fakeData));

        return $response->withStatus((int) $statusCode)->withBody($body)->withAddedHeader('Content-Type', $contentType);
    }

    private function createFaker(OpenApi $schema): OpenAPIFaker
    {
        if ($this->faker instanceof OpenAPIFaker) {
            return $this->faker;
        }

        $this->faker = OpenAPIFaker::createFromSchema($schema)->setOptions(array_filter([
            'minItems' => $this->options->getMaxItems(),
            'maxItems' => $this->options->getMaxItems(),
            'alwaysFakeOptionals' => $this->options->getAlwaysFakeOptionals(),
            'strategy' => $this->options->getStrategy(),
        ], fn ($v) => $v !== null));

        return $this->faker;
    }
}

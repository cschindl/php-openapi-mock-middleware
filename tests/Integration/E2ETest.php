<?php

declare(strict_types=1);

namespace Cschindl\OpenApiMockMiddleware\Tests\Integration;

use cebe\openapi\spec\Parameter;
use cebe\openapi\spec\Schema;
use Cschindl\OpenApiMockMiddleware\OpenApiMockMiddleware;
use Cschindl\OpenApiMockMiddleware\OpenApiMockMiddlewareConfig;
use Cschindl\OpenApiMockMiddleware\OpenApiMockMiddlewareFactory;
use League\OpenAPIValidation\PSR7\SchemaFactory\YamlFactory;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Vural\OpenAPIFaker\Options;
use Vural\OpenAPIFaker\SchemaFaker\SchemaFaker;

use function file_get_contents;
use function implode;
use function is_array;
use function sprintf;
use function str_replace;

class E2ETest extends TestCase
{
    use ProphecyTrait;

    private const SPECS = [
        'petstore',
        'twitter',
        'uber',
        'uspto',
    ];

    /**
     * @dataProvider provideValidRequest
     */
    public function testHandleValidRequest(ServerRequestInterface $request, int $expectedStatusCode, string $yaml): void
    {
        $middleware = $this->createMiddleware($yaml);

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $response = $middleware->process($request, $handler->reveal());

        if ($response->getStatusCode() !== $expectedStatusCode) {
            self::fail($response->getBody()->__toString());
        }

        self::assertEquals((string) $expectedStatusCode, $response->getStatusCode());
    }

    /**
     * @return mixed[]
     */
    public function provideValidRequest(): array
    {
        $data = [];

        foreach (self::SPECS as $filename) {
            $yaml = file_get_contents(sprintf('%s/../specs/%s.yaml', __DIR__, $filename));

            try {
                $schema = (new YamlFactory((string) $yaml))->createSchema();
            } catch (Throwable) {
                continue;
            }

            foreach ($schema->paths->getPaths() as $path => $pathItem) {
                foreach ($pathItem->getOperations() as $method => $operation) {
                    if ($method !== 'get') {
                        continue;
                    }

                    if ($operation->responses === null) {
                        continue;
                    }

                    if ($operation->security !== null) {
                        continue;
                    }

                    $request = $this->prophesize(ServerRequestInterface::class);
                    $request->getHeader(OpenApiMockMiddleware::HEADER_OPENAPI_MOCK_ACTIVE)->willReturn(['true']);
                    $request->getMethod()->willReturn($method);

                    $path = str_replace('{scheme}', 'https', $schema->servers[0]->url . $path);
                    $queryParams = [];
                    $cookieParams = [];

                    /** @var Parameter $parameter*/
                    foreach ($operation->parameters as $parameter) {
                        if ($parameter->required !== true) {
                            continue;
                        }

                        /** @var Schema $parameterSchema */
                        $parameterSchema = $parameter->schema;
                        $fakeData = (new SchemaFaker($parameterSchema, (new Options())->setStrategy(Options::STRATEGY_STATIC)))->generate();
                        if (is_array($fakeData)) {
                            $fakeData = implode(',', $fakeData);
                        }

                        if ($parameter->in === 'path') {
                            $path = str_replace('{' . $parameter->name . '}', (string) $fakeData, $path);
                        }

                        if ($parameter->in === 'query') {
                            $queryParams[$parameter->name] = $fakeData;
                        }

                        if ($parameter->in === 'header') {
                            $request->getHeader($parameter->name)->willReturn([$parameter->example]);
                        }

                        if ($parameter->in !== 'cookie') {
                            continue;
                        }

                        $cookieParams[$parameter->name] = $parameter->example;
                    }

                    $request->getQueryParams()->willReturn($queryParams);
                    $request->getCookieParams()->willReturn($cookieParams);
                    $request->getUri()->willReturn(new Uri($path));

                    foreach ($operation->responses as $statusCode => $response) {
                        if ($statusCode === 'default') {
                            continue;
                        }

                        if ($statusCode !== 200) {
                            continue;
                        }

                        $request->getHeader(OpenApiMockMiddleware::HEADER_OPENAPI_MOCK_STATUSCODE)->willReturn([(string) $statusCode]);

                        foreach ($response->content as $contentType => $mediaType) {
                            if ($mediaType->schema === null) {
                                continue;
                            }

                            if ($contentType !== 'application/json') {
                                continue;
                            }

                            $request->getHeader(OpenApiMockMiddleware::HEADER_CONTENT_TYPE)->willReturn([$contentType]);

                            $data[$method . ':' . $path] = [
                                $request->reveal(),
                                $statusCode,
                                $yaml,
                            ];
                        }
                    }
                }
            }
        }

        return $data;
    }

    private function createMiddleware(string $yaml): OpenApiMockMiddleware
    {
        $validatorBuilder = (new ValidatorBuilder())->fromYaml($yaml);
        $psr17Factory = new Psr17Factory();

        $options = (new Options())
            ->setAlwaysFakeOptionals(true)
            ->setStrategy(Options::STRATEGY_STATIC);

        return OpenApiMockMiddlewareFactory::createFromValidatorBuilder(
            $validatorBuilder,
            new OpenApiMockMiddlewareConfig(true, true, $options),
            $psr17Factory,
            $psr17Factory
        );
    }
}

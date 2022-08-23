<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Tests\Integration;

use cebe\openapi\spec\OpenApi;
use Cschindl\OpenAPIMock\ErrorResponseGenerator;
use Cschindl\OpenAPIMock\OpenApiMockMiddleware;
use Cschindl\OpenAPIMock\ResponseFaker;
use League\OpenAPIValidation\PSR7\SchemaFactory\YamlFactory;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Vural\OpenAPIFaker\OpenAPIFaker;
use Vural\OpenAPIFaker\Options;
use Vural\OpenAPIFaker\SchemaFaker\SchemaFaker;

class E2ETest extends TestCase
{
    use ProphecyTrait;

    private const SPECS = [
        'petstore',
        // 'twitter',
        'uber',
        // 'uspto',
    ];

    /**
     * @dataProvider provideValidRequest
     */
    function testHandleValidRequest(ServerRequestInterface $request, string $yaml)
    {
        $middleware = $this->createMiddleware($yaml);

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $response = $middleware->process($request, $handler->reveal());

        if ($response->getStatusCode() !== 200) {
            self::fail($response->getBody()->__toString());
        }

        self::assertEquals('200', $response->getStatusCode());
    }

    /**
     * @return string[][]
     */
    public function provideValidRequest(): array
    {
        $data = [];

        foreach (self::SPECS as $filename) {
            $yaml = file_get_contents(sprintf('%s/../specs/%s.yaml', __DIR__, $filename));
            $schema = (new YamlFactory($yaml))->createSchema();

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
                    $request->getMethod()->willReturn($method);

                    $path = str_replace('{scheme}', 'https', $schema->servers[0]->url . $path);
                    $queryParams = [];
                    $headers = [];
                    $cookieParams = [];
                    foreach ($operation->parameters as $parameter) {
                        if ($parameter->required === true) {
                            $fakeData = (new SchemaFaker($parameter->schema, (new Options())->setStrategy(Options::STRATEGY_STATIC)))->generate();

                            if ($parameter->in === 'path') {
                                $path = str_replace('{' . $parameter->name . '}', $fakeData, $path);
                            }

                            if ($parameter->in === 'query') {
                                $queryParams[$parameter->name] = $fakeData;
                            }

                            if ($parameter->in === 'header') {
                                $headers[$parameter->name][0] = $parameter->example;
                            }

                            if ($parameter->in === 'cookie') {
                                $cookieParams[$parameter->name] = $parameter->example;
                            }
                        }
                    }

                    $request->getQueryParams()->willReturn($queryParams);
                    $request->getHeader(Argument::any())->willReturn($headers);
                    $request->getCookieParams()->willReturn($cookieParams);
                    $request->getUri()->willReturn(new Uri($path));

                    foreach ($operation->responses as $statusCode => $response) {
                        if ($statusCode == 'default') {
                            continue;
                        }

                        $request->getHeader(OpenApiMockMiddleware::HEADER_FAKER_STATUSCODE)->willReturn([(string) $statusCode]);

                        foreach ($response->content as $contentType => $mediaType) {
                            if ($mediaType->schema === null) {
                                continue;
                            }

                            $request->getHeader(OpenApiMockMiddleware::HEADER_CONTENT_TYPE)->willReturn([$contentType]);

                            $data[$method . ':' . $path] = [
                                $request->reveal(),
                                $yaml
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
        $settings = [
            'minItems' => 5,
            'maxItems' => 10,
            'alwaysFakeOptionals' => true,
            'strategy' => Options::STRATEGY_STATIC,
        ];

        return new OpenApiMockMiddleware(
            $validatorBuilder,
            new ResponseFaker(
                $psr17Factory,
                $psr17Factory,
                $settings
            ),
            new ErrorResponseGenerator(
                $psr17Factory,
                $psr17Factory,
            )
        );
    }
}

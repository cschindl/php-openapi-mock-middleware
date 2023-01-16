<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Tests\Integration;

use Cschindl\OpenAPIMock\OpenApiMockMiddleware;
use Cschindl\OpenAPIMock\Request\RequestHandler;
use Cschindl\OpenAPIMock\Response\ResponseFaker;
use Cschindl\OpenAPIMock\Response\ResponseHandler;
use Cschindl\OpenAPIMock\Validator\RequestValidator;
use Cschindl\OpenAPIMock\Validator\ResponseValidator;
use League\OpenAPIValidation\PSR7\SchemaFactory\YamlFactory;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Vural\OpenAPIFaker\Options;
use Vural\OpenAPIFaker\SchemaFaker\SchemaFaker;

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
    function testHandleValidRequest(ServerRequestInterface $request, int $expectedStatusCode, string $yaml)
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
                    $request->getHeader(OpenApiMockMiddleware::HEADER_FAKER_ACTIVE)->willReturn(['true']);
                    $request->getMethod()->willReturn($method);

                    $path = str_replace('{scheme}', 'https', $schema->servers[0]->url . $path);
                    $queryParams = [];
                    $cookieParams = [];
                    foreach ($operation->parameters as $parameter) {
                        if ($parameter->required === true) {
                            $fakeData = (new SchemaFaker($parameter->schema, (new Options())->setStrategy(Options::STRATEGY_STATIC)))->generate();
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

                            if ($parameter->in === 'cookie') {
                                $cookieParams[$parameter->name] = $parameter->example;
                            }
                        }
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

                        $request->getHeader(OpenApiMockMiddleware::HEADER_FAKER_STATUSCODE)->willReturn([(string) $statusCode]);

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
            'minItems' => 1,
            'maxItems' => 10,
            'alwaysFakeOptionals' => true,
            'strategy' => Options::STRATEGY_STATIC,
        ];
        $responseFaker = new ResponseFaker(
            $psr17Factory,
            $psr17Factory,
            $settings
        );

        return new OpenApiMockMiddleware(
            new RequestHandler($responseFaker),
            new RequestValidator($validatorBuilder),
            new ResponseHandler($responseFaker),
            new ResponseValidator($validatorBuilder)
        );
    }
}

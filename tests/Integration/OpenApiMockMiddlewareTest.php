<?php

declare(strict_types=1);

namespace Cschindl\OpenApiMockMiddleware\Tests\Integration;

use Cschindl\OpenApiMockMiddleware\OpenApiMockMiddleware;
use Cschindl\OpenApiMockMiddleware\OpenApiMockMiddlewareConfig;
use Cschindl\OpenApiMockMiddleware\OpenApiMockMiddlewareFactory;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Stream;
use Nyholm\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Vural\OpenAPIFaker\Options;

use function json_decode;

class OpenApiMockMiddlewareTest extends TestCase
{
    use ProphecyTrait;

    public function testHandleValidRequest(): void
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn(new Uri('http://localhost:4010/todos'));
        $request->getMethod()->willReturn('GET');
        $request->getCookieParams()->willReturn([]);
        $request->getHeader(OpenApiMockMiddleware::HEADER_CONTENT_TYPE)->willReturn(['application/json']);
        $request->getHeader(OpenApiMockMiddleware::HEADER_FAKER_ACTIVE)->willReturn(['true']);
        $request->getHeader(Argument::any())->willReturn([]);
        $request->getQueryParams()->willReturn([]);
        $handler = $this->prophesize(RequestHandlerInterface::class);

        $middleware = $this->createMiddleware($this->getTodosSpec());
        $response = $middleware->process($request->reveal(), $handler->reveal());

        $expected = [
            [
                'id' => 100,
                'name' => 'watering plants',
                'tag' => 'homework',
            ],
            [
                'id' => 101,
                'name' => 'prepare food',
                'tag' => 'homework',
            ],
        ];

        self::assertInstanceOf(ResponseInterface::class, $response);
        self::assertEquals($expected, json_decode($response->getBody()->__toString(), true));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeader('Content-Type')[0]);
    }

    public function testHandleInValidRequestForNoPathMatched(): void
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn(new Uri('http://localhost:4010/hello'));
        $request->getMethod()->willReturn('GET');
        $request->getCookieParams()->willReturn([]);
        $request->getHeader(OpenApiMockMiddleware::HEADER_CONTENT_TYPE)->willReturn(['application/json']);
        $request->getHeader(OpenApiMockMiddleware::HEADER_FAKER_ACTIVE)->willReturn(['true']);
        $request->getHeader(Argument::any())->willReturn([]);
        $request->getQueryParams()->willReturn([]);
        $handler = $this->prophesize(RequestHandlerInterface::class);

        $middleware = $this->createMiddleware($this->getTodosSpec());
        $response = $middleware->process($request->reveal(), $handler->reveal());

        $expected = [
            'type' => 'NO_PATH_AND_METHOD_MATCHED_ERROR',
            'title' => 'Route resolved, but no path matched',
            'detail' => 'OpenAPI spec contains no such operation [/hello,get]',
            'status' => 404,
        ];

        self::assertInstanceOf(ResponseInterface::class, $response);
        self::assertEquals($expected, json_decode($response->getBody()->__toString(), true));
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeader('Content-Type')[0]);
    }

    public function testHandleInValidRequestForNoMethodMatched(): void
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn(new Uri('http://localhost:4010/todos'));
        $request->getMethod()->willReturn('PUT');
        $request->getCookieParams()->willReturn([]);
        $request->getHeader(OpenApiMockMiddleware::HEADER_CONTENT_TYPE)->willReturn(['application/json']);
        $request->getHeader(OpenApiMockMiddleware::HEADER_FAKER_ACTIVE)->willReturn(['true']);
        $request->getHeader(Argument::any())->willReturn([]);
        $request->getQueryParams()->willReturn([]);
        $handler = $this->prophesize(RequestHandlerInterface::class);

        $middleware = $this->createMiddleware($this->getTodosSpec());
        $response = $middleware->process($request->reveal(), $handler->reveal());

        $expected = [
            'type' => 'NO_PATH_AND_METHOD_MATCHED_ERROR',
            'title' => 'Route resolved, but no path matched',
            'detail' => 'OpenAPI spec contains no such operation [/todos,put]',
            'status' => 404,
        ];

        self::assertInstanceOf(ResponseInterface::class, $response);
        self::assertEquals($expected, json_decode($response->getBody()->__toString(), true));
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeader('Content-Type')[0]);
    }

    public function testHandleInValidRequestForUnprocessableEntity(): void
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn(new Uri('http://localhost:4010/todos'));
        $request->getMethod()->willReturn('POST');
        $request->getCookieParams()->willReturn([]);
        $request->getHeader(OpenApiMockMiddleware::HEADER_CONTENT_TYPE)->willReturn(['application/json']);
        $request->getHeader(OpenApiMockMiddleware::HEADER_FAKER_ACTIVE)->willReturn(['true']);
        $request->getHeader(Argument::any())->willReturn([]);
        $request->getQueryParams()->willReturn([]);
        $request->getBody()->willReturn(Stream::create('{}'));
        $handler = $this->prophesize(RequestHandlerInterface::class);

        $middleware = $this->createMiddleware($this->getTodosSpec());
        $response = $middleware->process($request->reveal(), $handler->reveal());

        $expected = [
            'type' => 'UNPROCESSABLE_ENTITY',
            'title' => 'Invalid request',
            'detail' => 'Body does not match schema for content-type "application/json" for Request [post /todos]\nKeyword validation failed: Required property \'id\' must be present in the object',
            'status' => 422,
        ];

        self::assertInstanceOf(ResponseInterface::class, $response);
        self::assertEquals($expected, json_decode($response->getBody()->__toString(), true));
        self::assertSame(422, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeader('Content-Type')[0]);
    }

    public function testHandleInValidRequestForUnauthorized(): void
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn(new Uri('http://localhost:4010/todos'));
        $request->getMethod()->willReturn('DELETE');
        $request->getCookieParams()->willReturn([]);
        $request->getHeader(OpenApiMockMiddleware::HEADER_CONTENT_TYPE)->willReturn(['application/json']);
        $request->getHeader(OpenApiMockMiddleware::HEADER_FAKER_ACTIVE)->willReturn(['true']);
        $request->getHeader(Argument::any())->willReturn([]);
        $request->getQueryParams()->willReturn([]);
        $request->getBody()->willReturn(Stream::create('{}'));
        $handler = $this->prophesize(RequestHandlerInterface::class);

        $middleware = $this->createMiddleware($this->getTodosSpec());
        $response = $middleware->process($request->reveal(), $handler->reveal());

        $expected = [
            'type' => 'UNAUTHORIZED',
            'title' => 'Invalid security scheme used',
            'detail' => 'None of security schemas did match for Request [delete /todos]',
            'status' => 401,
        ];

        self::assertInstanceOf(ResponseInterface::class, $response);
        self::assertEquals($expected, json_decode($response->getBody()->__toString(), true));
        self::assertSame(401, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeader('Content-Type')[0]);
    }

    private function createMiddleware(string $yaml): OpenApiMockMiddleware
    {
        $validatorBuilder = (new ValidatorBuilder())->fromYaml($yaml);
        $psr17Factory = new Psr17Factory();

        $options = (new Options())
            ->setMinItems(1)
            ->setMaxItems(10)
            ->setAlwaysFakeOptionals(true)
            ->setStrategy(Options::STRATEGY_STATIC);

        return OpenApiMockMiddlewareFactory::createFromValidatorBuilder(
            $psr17Factory,
            $psr17Factory,
            $validatorBuilder,
            new OpenApiMockMiddlewareConfig(true, true, $options)
        );
    }

    private function getTodosSpec(): string
    {
        return <<<'YAML'
openapi: 3.0.2
paths:
  /todos:
    post:
      requestBody:
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/Todo'
            examples:
              textExample:
                $ref: '#/components/examples/AddTextExample'
      responses:
        '200':
          description: 'Add Todo Item'
          content: 
            application/json:
              schema:
                $ref: '#/components/schemas/Todo'
              examples: 
                textExample:
                  $ref: '#/components/examples/GetTextExample'
    get:
      responses:
        '200':
          description: 'Get Todo Items'
          content: 
            application/json:
              schema:
                $ref: '#/components/schemas/Todos'
              examples: 
                textExample:
                  $ref: '#/components/examples/GetTextExamples'
    delete:
      security:
        - apikey: []
      responses:
        '200':
          description: 'Get Todo Items'
          content: 
            application/json:
              schema:
                $ref: '#/components/schemas/Todos'
              examples: 
                textExample:
                    $ref: '#/components/examples/GetTextExamples'
components:
  securitySchemes:
    apikey:
      type: apiKey
      name: server_token
      in: query
  schemas:
    Todo:
      type: object
      required:
        - id
        - name
      properties:
        id:
          type: integer
          format: int64
        name:
          type: string
        tag:
          type: string
    Todos:
      type: array
      items:
        $ref: '#/components/schemas/Todo'
  examples:
    AddTextExample:
      summary: Add a todo example
      value:
        id: 100
        name: watering plants
        tag: homework
    GetTextExamples:
      summary: A todo example list
      value:
        - id: 100
          name: watering plants
          tag: homework
        - id: 101
          name: prepare food
          tag: homework
    GetTextExample:
      summary: A todo example
      value:
        id: 100
        name: watering plants
        tag: homework
YAML;
    }
}

<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Tests\Integration;

use Cschindl\OpenAPIMock\ErrorResponseGenerator;
use Cschindl\OpenAPIMock\OpenApiMockMiddleware;
use Cschindl\OpenAPIMock\ResponseFaker;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Vural\OpenAPIFaker\Options;

class OpenApiMockMiddlewareTest extends TestCase
{
    use ProphecyTrait;

    public function testHandleValidRequest(): void
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn(new Uri('http://localhost:4010/pets'));
        $request->getMethod()->willReturn('GET');
        $request->getCookieParams()->willReturn([]);
        $request->getHeader('Content-Type')->willReturn(['application/json']);
        $request->getQueryParams()->willReturn([]);
        $handler = $this->prophesize(RequestHandlerInterface::class);

        $yaml = <<<YAML
openapi: 3.0.1
paths:
  /pets:
    get:
      responses:
        200:
          description: Hey
YAML;

        $middleware = $this->createMiddleware($yaml);

        $response = $middleware->process($request->reveal(), $handler->reveal());

        self::assertInstanceOf(ResponseInterface::class, $response);
    }


    public function testRoutingExceptionForNoPathMatched(): void
    {
        $this->markTestSkipped('TODO');

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn(new Uri('http://localhost:4010/hello'));
        $request->getMethod()->willReturn('GET');
        $request->getCookieParams()->willReturn([]);
        $request->getHeader('Content-Type')->willReturn(['application/json']);
        $request->getQueryParams()->willReturn([]);
        $handler = $this->prophesize(RequestHandlerInterface::class);

        $yaml = <<<YAML
openapi: 3.0.1
paths:
  /pets:
    get:
      responses:
        200:
          description: Hey
YAML;

        $middleware = $this->createMiddleware($this->createYamlFileWithContent($yaml));

        $response = $middleware->process($request->reveal(), $handler->reveal());

        self::assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testRoutingExceptionForNoMethodMatched(): void
    {
        $this->markTestSkipped('TODO');

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn(new Uri('http://localhost:4010/hello'));
        $request->getMethod()->willReturn('GET');
        $request->getCookieParams()->willReturn([]);
        $request->getHeader('Content-Type')->willReturn(['application/json']);
        $request->getQueryParams()->willReturn([]);
        $handler = $this->prophesize(RequestHandlerInterface::class);

        $yaml = <<<YAML
openapi: 3.0.1
paths:
  /pets:
    get:
      responses:
        200:
          description: Hey
YAML;

        $middleware = $this->createMiddleware($this->createYamlFileWithContent($yaml));

        $response = $middleware->process($request->reveal(), $handler->reveal());

        self::assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testValidationExceptionForUnprocessableEntity(): void
    {
        $this->markTestSkipped('TODO');

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn(new Uri('http://localhost:4010/pet'));
        $request->getMethod()->willReturn('PUT');
        $request->getCookieParams()->willReturn([]);
        $request->getHeader('Content-Type')->willReturn(['application/json']);
        $request->getQueryParams()->willReturn([]);
        $handler = $this->prophesize(RequestHandlerInterface::class);

        $body = $this->prophesize(StreamInterface::class);
        $body->__toString()->willReturn('{}');
        $this->request->getBody()->willReturn($body);

        $yaml = <<<YAML
openapi: 3.0.1
paths:
  /pet:
    put:
      requestBody:
        content:
          application/json:
            schema:
              "\$ref": "#/components/schemas/Pet"
        required: true
      responses:
        '200':
          content:
            application/json:
              schema:
                "\$ref": "#/components/schemas/Pet"

components:
  schemas:
    Pet:
      required:
        - id
          type: object
          properties:
          id:
            type: integer
            format: int64
YAML;

        $middleware = $this->createMiddleware($this->createYamlFileWithContent($yaml));

        $response = $middleware->process($request->reveal(), $handler->reveal());

        self::assertInstanceOf(ResponseInterface::class, $response);
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

<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Tests\Unit;

use Cschindl\OpenAPIMock\Exception\RoutingException;
use Cschindl\OpenAPIMock\RequestValidator;
use InvalidArgumentException;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\OperationAddress;
use Nyholm\Psr7\Uri;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamException;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\ServerRequestInterface;

class RequestValidatorTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @return void
     */
    public function testValidateRequest(): void
    {
        /** @var ServerRequestInterface&ObjectProphecy */
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn(new Uri('http://localhost:4010/pet'));
        $request->getMethod()->willReturn('GET');
        $request->getCookieParams()->willReturn([]);
        $request->getHeader('Content-Type')->willReturn(['application/json']);
        $request->getQueryParams()->willReturn([]);

        $yaml = <<<YAML
openapi: 3.0.1
paths:
  /pet:
    get:
      responses:
        200:
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

        $validator = RequestValidator::fromPath($this->createYamlFileWithContent($yaml), null);

        $result = $validator->validateRequest($request->reveal());
        $this->assertInstanceOf(OperationAddress::class, $result);
    }

    /**
     * @return void
     */
    public function testInValidateRequest(): void
    {
        /** @var ServerRequestInterface&ObjectProphecy */
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn(new Uri('http://localhost:4010/hello'));
        $request->getMethod()->willReturn('GET');
        $request->getCookieParams()->willReturn([]);
        $request->getHeader('Content-Type')->willReturn(['application/json']);
        $request->getQueryParams()->willReturn([]);

        $yaml = <<<YAML
openapi: 3.0.1
paths:
  /pets:
    get:
      responses:
        200:
          description: Hey
YAML;

        $validator = RequestValidator::fromPath($this->createYamlFileWithContent($yaml), null);

        $this->expectException(ValidationFailed::class);

        $validator->validateRequest($request->reveal());
    }

    /**
     * @return void
     */
    public function testRoutingExceptionForNoResourceProvided(): void
    {
        /** @var ServerRequestInterface&ObjectProphecy */
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn(new Uri('http://localhost:4010/pets'));
        $request->getMethod()->willReturn('POST');
        $request->getCookieParams()->willReturn([]);
        $request->getHeader('Content-Type')->willReturn(['application/json']);
        $request->getQueryParams()->willReturn([]);

        $yaml = <<<YAML
openapi: 3.0.1
paths:
YAML;

        $validator = RequestValidator::fromPath($this->createYamlFileWithContent($yaml), null);

        $this->expectException(RoutingException::class);

        $validator->validateRequest($request->reveal());
    }

    /**
     * @param string $content
     * @return string
     * @throws vfsStreamException
     * @throws InvalidArgumentException
     */
    private function createYamlFileWithContent(string $content): string
    {
        $root = vfsStream::setup('root_dir');
        $file = vfsStream::newFile('spec.yaml');
        $file->setContent($content);
        $root->addChild($file);

        return $file->url();
    }
}

<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Tests\Unit;

use Cschindl\OpenAPIMock\Exception\RoutingException;
use Cschindl\OpenAPIMock\Exception\ValidationException;
use Cschindl\OpenAPIMock\RequestValidator;
use InvalidArgumentException;
use Laminas\Diactoros\Uri;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamException;
use PHPUnit\Framework\TestCase;
use Prophecy\Exception\Doubler\DoubleException;
use Prophecy\Exception\Doubler\InterfaceNotFoundException;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

class RequestValidatorTest extends TestCase
{
  use ProphecyTrait;

  /**
   * @var ServerRequestInterface&ObjectProphecy
   */
  private $request;

  /**
   * @throws DoubleException
   * @throws InterfaceNotFoundException
   */
  public function setUp(): void
  {
    parent::setUp();

    /** @var ServerRequestInterface */
    $this->request = $this->prophesize(ServerRequestInterface::class);
    $this->request->getCookieParams()->willReturn([]);
    $this->request->getHeader('Content-Type')->willReturn(['application/json']);
    $this->request->getQueryParams()->willReturn([]);
  }

  /**
   * @return void
   */
  public function testRoutingExceptionForNoResourceProvided(): void
  {
    $this->request->getUri()->willReturn(new Uri('http://localhost:4010/pets'));
    $this->request->getMethod()->willReturn('POST');

    $yaml = <<<YAML
openapi: 3.0.1
paths:
YAML;

    $validator = RequestValidator::fromPath($this->createYamlFileWithContent($yaml), null);

    $this->expectException(RoutingException::class);
    $this->expectExceptionMessage('Route not resolved, no resource provided');

    $validator->validateRequest($this->request->reveal());
  }

  /**
   * @return void
   */
  public function testRoutingExceptionForNoPathMatched(): void
  {
    $this->request->getUri()->willReturn(new Uri('http://localhost:4010/hello'));
    $this->request->getMethod()->willReturn('GET');

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

    $this->expectException(RoutingException::class);
    $this->expectExceptionMessage('Route not resolved, no path and method matched');

    $validator->validateRequest($this->request->reveal());
  }

  /**
   * @return void
   */
  public function testRoutingExceptionForNoMethodMatched(): void
  {
    $this->request->getUri()->willReturn(new Uri('http://localhost:4010/pets'));
    $this->request->getMethod()->willReturn('POST');

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

    $this->expectException(RoutingException::class);
    $this->expectExceptionMessage('Route not resolved, no path and method matched');

    $validator->validateRequest($this->request->reveal());
  }

  /**
   * @return void
   */
  public function testValidationExceptionForUnprocessableEntity(): void
  {
    $this->request->getUri()->willReturn(new Uri('http://localhost:4010/pet'));
    $this->request->getMethod()->willReturn('PUT');

    /** @var StreamInterface */
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

    $validator = RequestValidator::fromPath($this->createYamlFileWithContent($yaml), null);

    $this->expectException(ValidationException::class);

    $message = 'Body does not match schema for content-type "application/json" for Request [put /pet]';
    $message .= '\nKeyword validation failed: Required property \'id\' must be present in the object';
    $this->expectExceptionMessage($message);

    $validator->validateRequest($this->request->reveal());
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

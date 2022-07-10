<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Tests\Unit;

use Cschindl\OpenAPIMock\Exception\RoutingException;
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

        $this->request = $this->prophesize(ServerRequestInterface::class);
    }

    /**
     * @return void
     */
    public function testNoResourceProvidedError(): void
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
    public function testNoPathMatchedError(): void
    {
        $this->request->getUri()->willReturn(new Uri('http://localhost:4010/hello'));
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
        $this->expectExceptionMessage('Route not resolved, no path matched');

        $validator->validateRequest($this->request->reveal());
    }

    /**
     * @return void
     */
    public function testNoServerMatchedError(): void
    {
        $this->request->getUri()->willReturn(new Uri('http://localhost:4010/pet?__server=http%3A%2F%2Finvalidserver.com'));
        $this->request->getMethod()->willReturn('GET');

        $yaml = <<<YAML
openapi: 3.0.0
paths:
  '/pet':
    get:
      responses:
        200: {}
servers:
  - url: '{schema}://{host}/{basePath}'
    variables:
      schema:
        default: http
        enum:
          - http
          - https
      host:
        default: stoplight.io
        enum:
          - stoplight.io
          - dev.stoplight.io
      basePath:
        default: api
YAML;

        $validator = RequestValidator::fromPath($this->createYamlFileWithContent($yaml), null);

        $this->expectException(RoutingException::class);
        $this->expectExceptionMessage('Route not resolved, no server matched');

        $validator->validateRequest($this->request->reveal());
    }

    /**
     * @return void
     */
    public function testNoMethodMatchedError(): void
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
        $this->expectExceptionMessage('Route resolved, but no path matched');

        $validator->validateRequest($this->request->reveal());
    }

    /**
     * @return void
     */
    public function testNoServerConfigurationProvidedError(): void
    {
        $this->request->getUri()->willReturn(new Uri('http://localhost:4010/pets'));
        $this->request->getMethod()->willReturn('POST');

        $yaml = <<<YAML
openapi: 3.0.1
servers:
paths:
  /pets:
    get:
      responses:
        200:
          description: Hey
YAML;

        $validator = RequestValidator::fromPath($this->createYamlFileWithContent($yaml), null);

        $this->expectException(RoutingException::class);
        $this->expectExceptionMessage('Route not resolved, no server configuration provided');

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

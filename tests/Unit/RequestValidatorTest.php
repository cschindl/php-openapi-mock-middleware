<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Tests\Unit;

use Cschindl\OpenAPIMock\Exception\Routing;
use Cschindl\OpenAPIMock\RequestValidator;
use InvalidArgumentException;
use Laminas\Diactoros\Uri;
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
     * @var ServerRequestInterface&ObjectProphecy
     */
    private $request;

    public function setUp(): void
    {
        parent::setUp();

        $this->request = $this->prophesize(ServerRequestInterface::class);
        $this->request->getUri()->willReturn(new Uri('https://localhost/api/v1'));
        $this->request->getMethod()->willReturn('GET');
    }

    /**
     * @return void
     */
    public function testNoResourceProvidedError(): void
    {
        $yaml = <<<YAML
openapi: 3.0.0
YAML;

        $validator = RequestValidator::fromPath($this->createYamlFileWithContent($yaml), null);

        $this->expectException(Routing::class);
        $this->expectExceptionMessage('Route not resolved, no server matched');

        $validator->validateRequest($this->request->reveal());
    }

    /**
     * @return void
     */
    public function testNoRessourceProvidedError(): void
    {
        $yaml = <<<YAML
openapi: 3.0.1
servers:
    - url: https://localhost
paths:
YAML;

        $validator = RequestValidator::fromPath($this->createYamlFileWithContent($yaml), null);

        $this->expectException(Routing::class);
        $this->expectExceptionMessage('Route not resolved, no path matched');

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

<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock;

use cebe\openapi\spec\OpenApi;
use Cschindl\OpenAPIMock\Exception\NoSchemaFileFound;
use Cschindl\OpenAPIMock\Exception\Routing;
use InvalidArgumentException;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ServerRequestValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\ServerRequestInterface;

class RequestValidator
{
    /**
     * @var string
     */
    private $pathToYaml;

    /**
     * @var CacheItemPoolInterface|null
     */
    private $cache;

    /**
     * @var ServerRequestValidator|null
     */
    private $validator;

    /**
     * @var OpenApi|null
     */
    private $schema;

    /**
     * @param string $pathToYaml
     * @param CacheItemPoolInterface|null $cache
     */
    private function __construct(string $pathToYaml, ?CacheItemPoolInterface $cache = null)
    {
        $this->pathToYaml = $pathToYaml;
        $this->cache = $cache;
    }

    /**
     * @param string $pathToYaml
     * @param CacheItemPoolInterface|null $cache
     * @return RequestValidator
     */
    public static function fromPath(string $pathToYaml, ?CacheItemPoolInterface $cache = null): self
    {
        return new RequestValidator($pathToYaml, $cache);
    }

    /**
     * @param string $pathToYaml
     * @param ServerRequestInterface $request
     * @return OperationAddress
     * @throws NoSchemaFileFound
     * @throws InvalidArgumentException
     * @throws Routing
     * @throws ValidationFailed
     */
    public function validateRequest(ServerRequestInterface $request): OperationAddress
    {
        $validator = $this->createValidator($this->pathToYaml);

        $this->schema = $validator->getSchema();

        if (!isset($this->schema->servers) || empty($this->schema->servers) || $this->schema->servers[0]->url === '/') {
            throw Routing::forNoServerMatched();
        }
        if (!isset($this->schema->paths) || empty($this->schema->paths)) {
            throw Routing::forNoResourceProvided();
        }

        return $validator->validate($request);
    }

    /**
     * @return OpenApi|null
     */
    public function getSchema(): ?OpenApi
    {
        return $this->schema;
    }

    /**
     * @param string $pathToYaml
     * @return ServerRequestValidator
     * @throws NoSchemaFileFound
     * @throws InvalidArgumentException
     */
    private function createValidator(string $pathToYaml): ServerRequestValidator
    {
        if ($this->validator instanceof ServerRequestValidator) {
            return $this->validator;
        }

        if (!file_exists($pathToYaml)) {
            throw NoSchemaFileFound::forFilename($pathToYaml);
        }

        $yaml = file_get_contents($pathToYaml);
        $builder = (new ValidatorBuilder())->fromYaml($yaml);

        if ($this->cache instanceof CacheItemPoolInterface) {
            $builder = $builder->setCache($this->cache);
        }

        return $builder->getServerRequestValidator();
    }
}

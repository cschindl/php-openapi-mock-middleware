<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock;

use cebe\openapi\spec\OpenApi;
use Cschindl\OpenAPIMock\Exception\RoutingException;
use InvalidArgumentException;
use League\OpenAPIValidation\PSR7\Exception\NoPath;
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
    private $pathToSpecFile;

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
     * @param string $pathToSpecFile
     * @param CacheItemPoolInterface|null $cache
     */
    private function __construct(string $pathToSpecFile, ?CacheItemPoolInterface $cache = null)
    {
        $this->pathToSpecFile = $pathToSpecFile;
        $this->cache = $cache;
    }

    /**
     * @param string $pathToSpecFile
     * @param CacheItemPoolInterface|null $cache
     * @return RequestValidator
     */
    public static function fromPath(string $pathToSpecFile, ?CacheItemPoolInterface $cache = null): self
    {
        return new RequestValidator($pathToSpecFile, $cache);
    }

    /**
     * @param ServerRequestInterface $request
     * @return OperationAddress
     * @throws InvalidArgumentException
     * @throws RoutingException
     * @throws ValidationFailed
     */
    public function validateRequest(ServerRequestInterface $request): OperationAddress
    {
        $validator = $this->createValidator($this->pathToSpecFile);

        $this->schema = $validator->getSchema();

        if (!isset($this->schema->paths) || empty($this->schema->paths)) {
            throw RoutingException::forNoResourceProvided(NoPath::fromPath($request->getUri()->getPath()));
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
     * @param string $pathToSpecFile
     * @return ServerRequestValidator
     * @throws InvalidArgumentException
     */
    private function createValidator(string $pathToSpecFile): ServerRequestValidator
    {
        if ($this->validator instanceof ServerRequestValidator) {
            return $this->validator;
        }

        $yaml = file_get_contents($pathToSpecFile);
        $builder = (new ValidatorBuilder())->fromYaml($yaml);

        if ($this->cache instanceof CacheItemPoolInterface) {
            $builder = $builder->setCache($this->cache);
        }

        return $builder->getServerRequestValidator();
    }
}

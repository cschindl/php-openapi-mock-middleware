<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock;

use cebe\openapi\spec\OpenApi;
use Cschindl\OpenAPIMock\Exception\NoSchemaFileFound;
use InvalidArgumentException;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ResponseValidator as PSR7ResponseValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\ResponseInterface;

class ResponseValidator
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
     * @var PSR7ResponseValidator|null
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
     * @return ResponseValidator
     */
    public static function fromPath(string $pathToYaml, ?CacheItemPoolInterface $cache = null): self
    {
        return new ResponseValidator($pathToYaml, $cache);
    }

    /**
     * @param string $pathToYaml
     * @param OperationAddress $operationAddress
     * @param ResponseInterface $response
     * @throws NoSchemaFileFound
     * @throws InvalidArgumentException
     * @throws ValidationFailed
     */
    public function validateResonse(OperationAddress $operationAddress, ResponseInterface $response): void
    {
        $validator = $this->createValidator($this->pathToYaml);

        $this->schema = $validator->getSchema();

        $validator->validate($operationAddress, $response);
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
     * @return PSR7ResponseValidator
     * @throws NoSchemaFileFound
     * @throws InvalidArgumentException
     */
    private function createValidator(string $pathToYaml): PSR7ResponseValidator
    {
        if ($this->validator instanceof PSR7ResponseValidator) {
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

        return $builder->getResponseValidator();
    }
}

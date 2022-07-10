<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock;

use cebe\openapi\spec\OpenApi;
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
    private $pathToSpecFile;

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
     * @return ResponseValidator
     */
    public static function fromPath(string $pathToSpecFile, ?CacheItemPoolInterface $cache = null): self
    {
        return new ResponseValidator($pathToSpecFile, $cache);
    }

    /**
     * @param OperationAddress $operationAddress
     * @param ResponseInterface $response
     * @throws InvalidArgumentException
     * @throws ValidationFailed
     */
    public function validateResonse(OperationAddress $operationAddress, ResponseInterface $response): void
    {
        $validator = $this->createValidator($this->pathToSpecFile);

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
     * @param string $pathToSpecFile
     * @return PSR7ResponseValidator
     * @throws InvalidArgumentException
     */
    private function createValidator(string $pathToSpecFile): PSR7ResponseValidator
    {
        if ($this->validator instanceof PSR7ResponseValidator) {
            return $this->validator;
        }

        $yaml = file_get_contents($pathToSpecFile);
        $builder = (new ValidatorBuilder())->fromYaml($yaml);

        if ($this->cache instanceof CacheItemPoolInterface) {
            $builder = $builder->setCache($this->cache);
        }

        return $builder->getResponseValidator();
    }
}

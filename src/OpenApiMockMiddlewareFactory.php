<?php

declare(strict_types=1);

namespace Cschindl\OpenApiMockMiddleware;

use Cschindl\OpenApiMockMiddleware\Request\RequestHandler;
use Cschindl\OpenApiMockMiddleware\Response\ResponseFaker;
use Cschindl\OpenApiMockMiddleware\Response\ResponseHandler;
use Cschindl\OpenApiMockMiddleware\Validator\RequestValidator;
use Cschindl\OpenApiMockMiddleware\Validator\ResponseValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

class OpenApiMockMiddlewareFactory
{
    public static function createFromYamlFile(
        string $pathToYaml,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        OpenApiMockMiddlewareConfig|null $config = null,
        CacheItemPoolInterface|null $cache = null
    ): OpenApiMockMiddleware {
        $validatorBuilder = (new ValidatorBuilder())->fromYamlFile($pathToYaml);
        if ($cache instanceof CacheItemPoolInterface) {
            $validatorBuilder->setCache($cache);
        }

        return self::createFromValidatorBuilder(
            $responseFactory,
            $streamFactory,
            $validatorBuilder,
            $config
        );
    }

    public static function createFromJsonFile(
        string $pathToJson,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        OpenApiMockMiddlewareConfig|null $config = null,
        CacheItemPoolInterface|null $cache = null
    ): OpenApiMockMiddleware {
        $validatorBuilder = (new ValidatorBuilder())->fromJsonFile($pathToJson);
        if ($cache instanceof CacheItemPoolInterface) {
            $validatorBuilder->setCache($cache);
        }

        return self::createFromValidatorBuilder(
            $responseFactory,
            $streamFactory,
            $validatorBuilder,
            $config
        );
    }

    public static function createFromValidatorBuilder(
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        ValidatorBuilder $validatorBuilder,
        OpenApiMockMiddlewareConfig|null $config = null
    ): OpenApiMockMiddleware {
        $config = $config ?: new OpenApiMockMiddlewareConfig();

        $reponseFaker = new ResponseFaker(
            $responseFactory,
            $streamFactory,
            $config->getOptions()
        );

        return new OpenApiMockMiddleware(
            new RequestHandler($reponseFaker),
            new RequestValidator($validatorBuilder),
            new ResponseHandler($reponseFaker),
            new ResponseValidator($validatorBuilder),
            $config
        );
    }
}
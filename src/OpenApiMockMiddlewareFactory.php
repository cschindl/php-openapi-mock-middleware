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
    public static function createFromYaml(
        string $yaml,
        OpenApiMockMiddlewareConfig $config,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        CacheItemPoolInterface|null $cache = null
    ): OpenApiMockMiddleware {
        $validatorBuilder = (new ValidatorBuilder())->fromYaml($yaml);
        if ($cache instanceof CacheItemPoolInterface) {
            $validatorBuilder->setCache($cache);
        }

        return self::createFromValidatorBuilder(
            $validatorBuilder,
            $config,
            $responseFactory,
            $streamFactory
        );
    }

    public static function createFromYamlFile(
        string $pathToYaml,
        OpenApiMockMiddlewareConfig $config,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        CacheItemPoolInterface|null $cache = null
    ): OpenApiMockMiddleware {
        $validatorBuilder = (new ValidatorBuilder())->fromYamlFile($pathToYaml);
        if ($cache instanceof CacheItemPoolInterface) {
            $validatorBuilder->setCache($cache);
        }

        return self::createFromValidatorBuilder(
            $validatorBuilder,
            $config,
            $responseFactory,
            $streamFactory
        );
    }

    public static function createFromJson(
        string $json,
        OpenApiMockMiddlewareConfig $config,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        CacheItemPoolInterface|null $cache = null
    ): OpenApiMockMiddleware {
        $validatorBuilder = (new ValidatorBuilder())->fromJson($json);
        if ($cache instanceof CacheItemPoolInterface) {
            $validatorBuilder->setCache($cache);
        }

        return self::createFromValidatorBuilder(
            $validatorBuilder,
            $config,
            $responseFactory,
            $streamFactory
        );
    }

    public static function createFromJsonFile(
        string $pathToJson,
        OpenApiMockMiddlewareConfig $config,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        CacheItemPoolInterface|null $cache = null
    ): OpenApiMockMiddleware {
        $validatorBuilder = (new ValidatorBuilder())->fromJsonFile($pathToJson);
        if ($cache instanceof CacheItemPoolInterface) {
            $validatorBuilder->setCache($cache);
        }

        return self::createFromValidatorBuilder(
            $validatorBuilder,
            $config,
            $responseFactory,
            $streamFactory
        );
    }

    public static function createFromValidatorBuilder(
        ValidatorBuilder $validatorBuilder,
        OpenApiMockMiddlewareConfig $config,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
    ): OpenApiMockMiddleware {
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

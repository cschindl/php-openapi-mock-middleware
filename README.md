# php-openapi-mock-middleware

![Tests](https://github.com/cschindl/php-openapi-mock-middleware/workflows/Tests/badge.svg)
[![PHPStan](https://img.shields.io/badge/PHPStan-Level%20Max-brightgreen.svg?style=flat&logo=php)](https://phpstan.org)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

PSR-15 Middleware that simulates the API responses using an OpenAPI schema.

Define requests/responses using the [OpenAPI schema](https://www.openapis.org) and this data is immediately available, so development/testing against this API can begin even though the functionality has not yet been implemented.

## Requirements
- PHP >= 8.0
- [PSR-17](https://github.com/php-fig/http-factory) HTTP factories implementation 
- [PSR-15](https://github.com/php-fig/http-server-middleware) HTTP server middleware dispatcher
- [PSR-6](https://github.com/php-fig/cache) Caching interface implementation  (optional)

## Installation

You can install the package via composer:

```bash
composer require cschindl/php-openapi-mock-middleware
```

## Example usage
To see how to use and extend `OpenApiMockMiddleware`, have a look at our [example project](https://github.com/cschindl/php-openapi-mock-server).

## Usage
First you need to create an instance of `OpenApiMockMiddleware` with your schema that you want to fake data from. You can use `createFromYamlFile`,  `createFromJsonFile`, `createFromYaml` or `createFromJson` to create an instance of `OpenApiMockMiddleware`.

```php
use Cschindl\OpenApiMockMiddleware\OpenApiMockMiddlewareConfig;
use Cschindl\OpenApiMockMiddleware\OpenApiMockMiddlewareFactory;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/** @var ContainerInterface $container */
$container = require _DIR__ . '/config/container.php';

/** @var ResponseFactoryInterface $responseFactory */
$responseFactory = $container->get(ResponseFactoryInterface::class);

/** @var StreamFactoryInterface $responseFactory */
$streamFactory = $container->get(StreamFactoryInterface::class);

/** @var CacheItemPoolInterface|null $cache */
$cache = $container->get(CacheItemPoolInterface::class);

$pathToOpenApiFile = _DIR__ . '/data/openapi.yaml';
$config = new OpenApiMockMiddlewareConfig();

$openApiMockMiddleware = OpenApiMockMiddlewareFactory::createFromYamlFile(
    $pathToOpenApiFile,
    $config,
    $responseFactory,
    $streamFactory,
    $cache
);
```
After that, register the middleware.

```php
use Cschindl\OpenApiMockMiddleware\OpenApiMockMiddleware;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

$app = new MiddlewareRunner();
$app->add($openApiMockMiddleware);

// To enable the middleware, add this header to your requests
// If this header is not present in the request, the middleware will skip to the next handler
$prepareOpenApiMiddleware = function (
    ServerRequestInterface $request,
    RequestHandlerInterface $handler
) {
    return $handler->handle(
        $request->withAddedHeader(
            OpenApiMockMiddleware::HEADER_OPENAPI_MOCK_ACTIVE,
            'true'
        )
    );
);
// Make sure that this middleware is called before $openApiMockMiddleware
$app->add($prepareOpenApiMiddleware);

$app->run($request, $response);
```

## Options
There are some options you can use to modify some behaviour. 
```php
$settings = [
    'validateRequest' => true,
    'validateResponse' => true,
    'faker' => [
        'minItems' => 1,
        'maxItems' => 10,
        'alwaysFakeOptionals' => false,
        'strategy' => Options::STRATEGY_STATIC,
    ],
];

// @see https://github.com/canvural/php-openapi-faker#options
$fakerOptions = (new Options())
    ->setMinItems($settings['faker']['minItems'])
    ->setMaxItems($settings['faker']['maxItems'])
    ->setAlwaysFakeOptionals($settings['faker']['alwaysFakeOptionals'])
    ->setStrategy($settings['faker']['strategy']);

$config = new OpenApiMockMiddlewareConfig(
    $settings['validateRequest'],
    $settings['validateResponse'],
    $fakerOptions
);
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Credits

People:
- [Carsten Schindler](https://github.com/cschindl)
- [All Contributors](../../contributors)

Resources:
- [canvural/php-openapi-faker](https://github.com/canvural/php-openapi-faker)
- [cebe/php-openapi](https://github.com/cebe/php-openapi)
- [league/openapi-psr7-validator](https://github.com/thephpleague/openapi-psr7-validator)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

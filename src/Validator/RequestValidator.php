<?php

declare(strict_types=1);

namespace Cschindl\OpenApiMockMiddleware\Validator;

use cebe\openapi\spec\OpenApi;
use Cschindl\OpenApiMockMiddleware\Exception\RoutingException;
use League\OpenAPIValidation\PSR7\Exception\NoPath;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\PathFinder;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class RequestValidator
{
    public function __construct(
        private ValidatorBuilder $validatorBuilder
    ) {
    }

    public function parse(ServerRequestInterface $request, bool $validate): RequestValidatorResult
    {
        $requestValidator = $this->validatorBuilder->getServerRequestValidator();

        if ($validate) {
            try {
                $operationAddress = $requestValidator->validate($request);
                $schema = $requestValidator->getSchema();

                if ($schema->paths->count() === 0) {
                    return new RequestValidatorResult(
                        $schema,
                        $operationAddress,
                        RoutingException::forNoResourceProvided(NoPath::fromPath($request->getUri()->getPath()))
                    );
                }

                return new RequestValidatorResult($schema, $operationAddress);
            } catch (Throwable $th) {
                $schema = $requestValidator->getSchema();
                $operationAddress = $this->findOperationAddress($schema, $request);

                return new RequestValidatorResult($schema, $operationAddress, $th);
            }
        }

        $schema = $requestValidator->getSchema();
        $operationAddress = $this->findOperationAddress($schema, $request);

        return new RequestValidatorResult($schema, $operationAddress);
    }

    private function findOperationAddress(OpenApi $schema, ServerRequestInterface $request): OperationAddress
    {
        $pathFinder = new PathFinder($schema, $request->getUri()->getPath(), $request->getMethod());
        $paths = $pathFinder->search();

        return $paths[0] ?? new OperationAddress($request->getUri()->getPath(), $request->getMethod());
    }
}

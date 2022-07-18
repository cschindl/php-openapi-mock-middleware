<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Exception;

use Throwable;

class RoutingException extends RequestException
{
    public const NO_RESOURCE_PROVIDED_ERROR = 'NO_RESOURCE_PROVIDED_ERROR';
    public const NO_PATH_MATCHED_ERROR = 'NO_PATH_MATCHED_ERROR';
    public const NO_PATH_AND_METHOD_MATCHED_ERROR = 'NO_PATH_AND_METHOD_MATCHED_ERROR';
    public const NO_PATH_AND_METHOD_AND_RESPONSE_CODE_MATCHED_ERROR = 'NO_PATH_AND_METHOD_AND_RESPONSE_CODE_MATCHED_ERROR';

    /**
     * @param Throwable|null $previous
     * @return RoutingException
     */
    public static function forNoResourceProvided(?Throwable $previous = null): self
    {
        $title = 'Route not resolved, no resource provided';
        $detail = $previous !== null ? $previous->getMessage() : '';

        return new self(self::NO_RESOURCE_PROVIDED_ERROR, $title, $detail, 404);
    }

    /**
     * @param Throwable|null $previous
     * @return RoutingException
     */
    public static function forNoPathMatched(?Throwable $previous = null): self
    {
        $title = 'Route not resolved, no path matched';
        $detail = $previous !== null ? $previous->getMessage() : '';

        return new self(self::NO_PATH_MATCHED_ERROR, $title, $detail, 404);
    }

    /**
     * @param Throwable|null $previous
     * @return RoutingException
     */
    public static function forNoPathAndMethodMatched(?Throwable $previous = null): self
    {
        $title = 'Route resolved, but no path matched';
        $detail = $previous !== null ? $previous->getMessage() : '';

        return new self(self::NO_PATH_AND_METHOD_MATCHED_ERROR, $title, $detail, 405);
    }

    /**
     * @param Throwable|null $previous
     * @return RoutingException
     */
    public static function forNoPathAndMethodAndResponseCodeMatched(?Throwable $previous = null): self
    {
        $title = 'Route resolved, but no path, method or response code matched';
        $detail = $previous !== null ? $previous->getMessage() : '';

        return new self(self::NO_PATH_AND_METHOD_AND_RESPONSE_CODE_MATCHED_ERROR, $title, $detail, 405);
    }
}

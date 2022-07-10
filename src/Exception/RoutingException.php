<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Exception;

use InvalidArgumentException;
use Throwable;

class RoutingException extends InvalidArgumentException
{
    private const NO_RESOURCE_PROVIDED_ERROR = 'NO_RESOURCE_PROVIDED_ERROR';
    private const NO_PATH_MATCHED_ERROR = 'NO_PATH_MATCHED_ERROR';
    private const NO_SERVER_MATCHED_ERROR = 'NO_SERVER_MATCHED_ERROR';
    private const NO_METHOD_MATCHED_ERROR = 'NO_METHOD_MATCHED_ERROR';
    private const NO_SERVER_CONFIGURATION_PROVIDED_ERROR = 'NO_SERVER_CONFIGURATION_PROVIDED_ERROR';

    /**
     * @var string
     */
    private $type;

    /**
     * @param string $type
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $type, string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return RoutingException
     */
    public static function forNoResourceProvided(): self
    {
        $message = sprintf("Route not resolved, no resource provided");

        return new self(self::NO_RESOURCE_PROVIDED_ERROR, $message, 404);
    }

    /**
     * @return RoutingException
     */
    public static function forNoPathMatched(): self
    {
        $message = sprintf("Route not resolved, no path matched");

        return new self(self::NO_PATH_MATCHED_ERROR, $message, 404);
    }

    /**
     * @return RoutingException
     */
    public static function forNoServerMatched(): self
    {
        $message = sprintf("Route not resolved, no server matched");

        return new self(self::NO_SERVER_MATCHED_ERROR, $message, 404);
    }

    /**
     * @return RoutingException
     */
    public static function forNoMethodMatched(): self
    {
        $message = sprintf("Route resolved, but no path matched");

        return new self(self::NO_METHOD_MATCHED_ERROR, $message, 405);
    }

    /**
     * @return RoutingException
     */
    public static function forNoServerConfigurationProvided(): self
    {
        $message = sprintf("Route not resolved, no server configuration provided");

        return new self(self::NO_SERVER_CONFIGURATION_PROVIDED_ERROR, $message, 404);
    }
}

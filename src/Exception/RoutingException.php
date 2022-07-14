<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Exception;

use InvalidArgumentException;
use Throwable;

class RoutingException extends InvalidArgumentException
{
    private const NO_RESOURCE_PROVIDED_ERROR = 'NO_RESOURCE_PROVIDED_ERROR';
    private const NO_PATH_AND_METHOD_MATCHED_ERROR = 'NO_PATH_AND_METHOD_MATCHED_ERROR';

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
        $message = "Route not resolved, no resource provided";

        return new self(self::NO_RESOURCE_PROVIDED_ERROR, $message, 404);
    }

    /**
     * @return RoutingException
     */
    public static function forNoPathAndMethodMatched(): self
    {
        $message = "Route not resolved, no path and method matched";

        return new self(self::NO_PATH_AND_METHOD_MATCHED_ERROR, $message, 405);
    }
}

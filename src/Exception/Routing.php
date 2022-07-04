<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Exception;

use Exception;
use Throwable;

class Routing extends Exception
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

    public static function forNoResourceProvided(): self
    {
        $message = sprintf("Route not resolved, no path matched");

        return new self(self::NO_RESOURCE_PROVIDED_ERROR, $message, 404);
    }
}

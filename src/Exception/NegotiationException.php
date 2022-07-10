<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Exception;

use InvalidArgumentException;
use Throwable;

class NegotiationException extends InvalidArgumentException
{
    private const NO_COMPLEX_OBJECT_TEXT = 'NO_COMPLEX_OBJECT_TEXT';
    private const NO_RESPONSE_DEFINED = 'NO_RESPONSE_DEFINED';
    private const INVALID_CONTENT_TYPE = 'INVALID_CONTENT_TYPE';

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
     * @return NegotiationException
     */
    public static function forNoComplexObjectText(): self
    {
        $message = sprintf("Cannot serialise complex objects as text");

        return new self(self::NO_COMPLEX_OBJECT_TEXT, $message, 500);
    }

    /**
     * @return NegotiationException
     */
    public static function forNoResponseDefined(): self
    {
        $message = sprintf("No response defined for the selected operation");

        return new self(self::NO_RESPONSE_DEFINED, $message, 500);
    }

    /**
     * @return NegotiationException
     */
    public static function forInvalidContentType(): self
    {
        $message = sprintf("Supported content types: list");

        return new self(self::INVALID_CONTENT_TYPE, $message, 415);
    }
}

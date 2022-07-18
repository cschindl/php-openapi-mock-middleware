<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Exception;

use Throwable;

class NegotiationException extends RequestException
{
    public const NO_COMPLEX_OBJECT_TEXT = 'NO_COMPLEX_OBJECT_TEXT';
    public const NO_RESPONSE_DEFINED = 'NO_RESPONSE_DEFINED';
    public const INVALID_CONTENT_TYPE = 'INVALID_CONTENT_TYPE';

    /**
     * @param Throwable|null $previous
     * @return NegotiationException
     */
    public static function forNoComplexObjectText(?Throwable $previous = null): self
    {
        $title = 'Cannot serialise complex objects as text';
        $detail = $previous !== null ? $previous->getMessage() : '';

        return new self(self::NO_COMPLEX_OBJECT_TEXT, $title, $detail, 500, $previous);
    }

    /**
     * @param Throwable|null $previous
     * @return NegotiationException
     */
    public static function forNoResponseDefined(?Throwable $previous = null): self
    {
        $title = 'No response defined for the selected operation';
        $detail = $previous !== null ? $previous->getMessage() : '';

        return new self(self::NO_RESPONSE_DEFINED, $title, $detail, 500, $previous);
    }

    /**
     * @param Throwable|null $previous
     * @return NegotiationException
     */
    public static function forInvalidContentType(?Throwable $previous = null): self
    {
        $title = 'Supported content types: list';
        $detail = $previous !== null ? $previous->getMessage() : '';

        return new self(self::INVALID_CONTENT_TYPE, $title, $detail, 415, $previous);
    }
}

<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Exception;

use Throwable;

class UnknownException extends RequestException
{
    public const UNEXPECTED_ERROR_OCCURRED = 'UNEXPECTED_ERROR_OCCURRED';

    /**
     * @return UnknownException
     */
    public static function forUnexpectedErrorOccurred(?Throwable $previous = null): self
    {
        $title = 'Unexpected error occurred';
        $detail = $previous !== null ? $previous->getMessage() : '';

        return new self(self::UNEXPECTED_ERROR_OCCURRED, $title, $detail, 500, $previous);
    }
}

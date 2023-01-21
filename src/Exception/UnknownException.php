<?php

declare(strict_types=1);

namespace Cschindl\OpenApiMockMiddleware\Exception;

use Throwable;

class UnknownException extends RequestException
{
    public const UNEXPECTED_ERROR_OCCURRED = 'UNEXPECTED_ERROR_OCCURRED';

    /**
     * @return UnknownException
     */
    public static function forUnexpectedErrorOccurred(Throwable|null $previous = null): self
    {
        $title = 'Unexpected error occurred';
        $detail = $previous?->getMessage() ?? '';

        return new self(self::UNEXPECTED_ERROR_OCCURRED, $title, $detail, 500, $previous);
    }
}

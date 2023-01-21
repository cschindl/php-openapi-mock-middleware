<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Exception;

use Throwable;

class SecurityException extends RequestException
{
    public const UNAUTHORIZED = 'UNAUTHORIZED';

    /**
     * @return SecurityException
     */
    public static function forUnauthorized(Throwable|null $previous = null): self
    {
        $title = 'Invalid security scheme used';
        $detail = $previous?->getMessage() ?? '';

        return new self(self::UNAUTHORIZED, $title, $detail, 401, $previous);
    }
}

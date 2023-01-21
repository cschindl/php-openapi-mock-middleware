<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Exception;

use Throwable;

use function implode;

class ValidationException extends RequestException
{
    public const UNPROCESSABLE_ENTITY = 'UNPROCESSABLE_ENTITY';
    public const NOT_ACCEPTABLE = 'NOT_ACCEPTABLE';
    public const NOT_FOUND = 'NOT_FOUND';
    public const VIOLATIONS = 'VIOLATIONS';

    /**
     * @return ValidationException
     */
    public static function forUnprocessableEntity(Throwable|null $previous = null): self
    {
        $title = 'Invalid request';

        $detail = [];

        if ($previous !== null) {
            $detail[] = $previous->getMessage();

            if ($previous->getPrevious() !== null) {
                $detail[] = $previous->getPrevious()->getMessage();
            }
        }

        return new self(self::UNPROCESSABLE_ENTITY, $title, implode('\n', $detail), 422, $previous);
    }

    /**
     * @return ValidationException
     */
    public static function forNotAcceptable(Throwable|null $previous = null): self
    {
        $title = 'The server cannot produce a representation for your accept header';
        $detail = $previous?->getMessage() ?? '';

        return new self(self::NOT_ACCEPTABLE, $title, $detail, 406, $previous);
    }

    /**
     * @return ValidationException
     */
    public static function forNotFound(Throwable|null $previous = null): self
    {
        $title = 'The server cannot find the requested content';
        $detail = $previous?->getMessage() ?? '';

        return new self(self::NOT_FOUND, $title, $detail, 404, $previous);
    }

    /**
     * @return ValidationException
     */
    public static function forViolations(Throwable|null $previous = null): self
    {
        $title = 'Request/Response not valid';
        $detail = $previous?->getMessage() ?? '';

        return new self(self::VIOLATIONS, $title, $detail, 500, $previous);
    }
}

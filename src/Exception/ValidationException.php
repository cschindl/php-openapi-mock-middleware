<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Exception;

use InvalidArgumentException;
use Throwable;

class ValidationException extends InvalidArgumentException
{
    private const UNPROCESSABLE_ENTITY = 'UNPROCESSABLE_ENTITY';
    private const NOT_ACCEPTABLE = 'NOT_ACCEPTABLE';
    private const NOT_FOUND = 'NOT_FOUND';
    private const VIOLATIONS = 'VIOLATIONS';

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
     * @return ValidationException
     */
    public static function forUnprocessableEntity(): self
    {
        $message = sprintf("Invalid request");

        return new self(self::UNPROCESSABLE_ENTITY, $message, 422);
    }

    /**
     * @return ValidationException
     */
    public static function forNotAcceptable(): self
    {
        $message = sprintf("The server cannot produce a representation for your accept header");

        return new self(self::NOT_ACCEPTABLE, $message, 406);
    }

    /**
     * @return ValidationException
     */
    public static function forNotFound(): self
    {
        $message = sprintf("The server cannot find the requested content");

        return new self(self::NOT_FOUND, $message, 404);
    }

    /**
     * @return ValidationException
     */
    public static function forViolations(): self
    {
        $message = sprintf("Request/Response not valid");

        return new self(self::VIOLATIONS, $message, 500);
    }
}

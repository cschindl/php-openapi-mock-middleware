<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Exception;

use InvalidArgumentException;
use Throwable;

class ValidationException extends InvalidArgumentException implements RFC7807Interface
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
     * @var string
     */
    private $title;

    /**
     * @param string $type
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $type, string $title, string $detail = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($detail, $code, $previous);

        $this->type = $type;
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return ValidationException
     */
    public static function forUnprocessableEntity(Throwable $previous): self
    {
        $title = sprintf("Invalid request");
        
        $detail = [];
        $detail[] = $previous->getMessage();

        if ($previous->getPrevious() !== null) {
            $detail[] = $previous->getPrevious()->getMessage();
        }

        return new self(self::UNPROCESSABLE_ENTITY, $title, implode('\n', $detail), 422, $previous);
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

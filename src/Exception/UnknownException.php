<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Exception;

use RuntimeException;
use Throwable;

class UnknownException extends RuntimeException
{
    private const UNEXPECTED_ERROR_OCCURRED = 'UNEXPECTED_ERROR_OCCURRED';

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
     * @param Throwable $throwable
     * @return UnknownException
     */
    public static function forUnexpectedErrorOccurred(Throwable $throwable): self
    {
        $message = sprintf("Unexpected error occurred");

        return new self(self::UNEXPECTED_ERROR_OCCURRED, $message, 500, $throwable);
    }
}

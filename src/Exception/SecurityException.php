<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Exception;

use InvalidArgumentException;
use Throwable;

class SecurityException extends InvalidArgumentException
{
    private const UNAUTHORIZED = 'UNAUTHORIZED';

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
     * @return SecurityException
     */
    public static function forUnauthorized(): self
    {
        $message = sprintf("Invalid security scheme used");

        return new self(self::UNAUTHORIZED, $message, 401);
    }
}

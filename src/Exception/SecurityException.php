<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Exception;

use InvalidArgumentException;
use Throwable;

class SecurityException extends InvalidArgumentException implements RFC7807Interface
{
    private const UNAUTHORIZED = 'UNAUTHORIZED';

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
     * @param Throwable|null $previous
     * @return SecurityException
     */
    public static function forUnauthorized(Throwable $previous = null): self
    {
        $title = "Invalid security scheme used";

        return new self(self::UNAUTHORIZED, $title, $previous !== null ? $previous->getMessage() : $title, 401, $previous);
    }
}

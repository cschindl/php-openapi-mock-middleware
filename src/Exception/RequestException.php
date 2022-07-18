<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Exception;

use InvalidArgumentException;
use Throwable;

class RequestException extends InvalidArgumentException implements RFC7807Interface
{
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
     * @param string $title
     * @param string|null $detail
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $type, string $title, ?string $detail = null, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($detail ?? $title, $code, $previous);

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
}

<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Exception;

use InvalidArgumentException;
use Throwable;

class RequestException extends InvalidArgumentException implements RFC7807Interface
{
    private string $type;

    private string $title;

    public function __construct(string $type, string $title, ?string $detail = null, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($detail ?? $title, $code, $previous);

        $this->type = $type;
        $this->title = $title;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTitle(): string
    {
        return $this->title;
    }
}

<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Exception;

use Throwable;

interface RFC7807Interface extends Throwable
{
    public function getType(): string;

    public function getTitle(): string;
}

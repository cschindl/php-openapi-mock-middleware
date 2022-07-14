<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Exception;

interface RFC7807Interface
{
    /**
     * @return string
     */
    public function getType(): string;

    /**
     * @return string
     */
    public function getTitle(): string;

    /**
     * @return string
     */
    public function getMessage();

    /**
     * @return int
     */
    public function getCode();
}

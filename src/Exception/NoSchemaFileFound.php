<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Exception;

use Exception;

class NoSchemaFileFound extends Exception
{
    public static function forFilename(string $name): self
    {
        return new self(sprintf('OpenAPI spec file not found: %s.', $name));
    }
}

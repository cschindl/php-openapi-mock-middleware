<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock\Exception;

use Exception;

class Routing extends Exception
{
    private const NO_RESOURCE_PROVIDED_ERROR = 'NO_RESOURCE_PROVIDED_ERROR';
    private const NO_PATH_MATCHED_ERROR = 'NO_PATH_MATCHED_ERROR';
    private const NO_SERVER_MATCHED_ERROR = 'NO_SERVER_MATCHED_ERROR';
    private const NO_METHOD_MATCHED_ERROR = 'NO_METHOD_MATCHED_ERROR';
    private const NO_SERVER_CONFIGURATION_PROVIDED_ERROR = 'NO_SERVER_CONFIGURATION_PROVIDED_ERROR';

    public static function forNoPathMatching(string $path): self
    {
        // "type" => "NO_PATH_MATCHED_ERROR",
        // "title" => "Route not resolved, no path matched",
        $message = sprintf("The route %s hasn't been found in the specification file", $path);

        return new self($message, 404);
    }
}
